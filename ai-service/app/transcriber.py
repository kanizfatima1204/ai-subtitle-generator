import os
import re
import logging
from typing import Any

logger = logging.getLogger(__name__)

# Check if whisperx is installed
try:
    import whisperx
    HAS_WHISPERX = True
except ImportError:
    HAS_WHISPERX = False

from faster_whisper import WhisperModel


def _is_hallucinated(text: str) -> bool:
    """
    Detect common Whisper hallucination patterns and return True if the
    segment should be discarded.

    Patterns caught:
      1. Single character/syllable repeated 6+ times  (e.g. "রররররররররর")
      2. Short token (<= 4 chars) that fills > 60 % of the text
      3. Text is almost entirely punctuation / special chars
      4. Extremely long word with no spaces (>= 40 chars) — stuck-loop artefact
    """
    if not text:
        return True

    stripped = text.strip()

    # Pattern 1 – a Unicode character (or short cluster) repeated 6+ times
    if re.search(r'(.{1,3})\1{5,}', stripped):
        return True

    # Pattern 2 – token repetition: split on whitespace, check most-common token
    tokens = stripped.split()
    if len(tokens) >= 4:
        from collections import Counter
        most_common_token, freq = Counter(tokens).most_common(1)[0]
        if freq / len(tokens) > 0.6:
            return True

    # Pattern 3 – almost no alphabetic content (only symbols / numbers)
    alpha_chars = sum(1 for c in stripped if c.isalpha())
    if len(stripped) > 5 and alpha_chars / len(stripped) < 0.3:
        return True

    # Pattern 4 – single very long "word" with no spaces (stuck loop)
    if tokens and max(len(t) for t in tokens) >= 40:
        return True

    return False


class Transcriber:
    """
    High-performance transcription pipeline supporting both faster-whisper
    and whisperx with forced alignment fallback.

    Supports:
      - Bengali ('bn')
      - English ('en')
      - Auto language detection
      - Word-level timestamps
    """

    def __init__(
        self,
        model_name: str = "base",
        device: str | None = None,
        compute_type: str | None = None,
    ):
        self.model_name = model_name
        self.device = device or os.getenv("WHISPER_DEVICE", "cpu")

        # CPU default compute_type: int8 / float32
        default_compute = "int8" if self.device == "cpu" else "float16"
        self.compute_type = compute_type or os.getenv("WHISPER_COMPUTE_TYPE", default_compute)

        self.model = None
        self.engine = "faster-whisper"

    def _load_model(self):
        if self.model is None:
            if HAS_WHISPERX and self.device == "cuda":
                try:
                    logger.info(f"Loading WhisperX model '{self.model_name}' on {self.device} ({self.compute_type})...")
                    self.model = whisperx.load_model(
                        self.model_name,
                        self.device,
                        compute_type=self.compute_type,
                    )
                    self.engine = "whisperx"
                    return self.model
                except Exception as ex:
                    logger.warning(f"Failed to load WhisperX, falling back to faster-whisper: {ex}")

            try:
                logger.info(f"Loading faster-whisper model '{self.model_name}' on {self.device} ({self.compute_type})...")
                self.model = WhisperModel(
                    self.model_name,
                    device=self.device,
                    compute_type=self.compute_type,
                    cpu_threads=4,
                )
                self.engine = "faster-whisper"
            except Exception as ex:
                logger.error(f"Failed to load Whisper model '{self.model_name}': {ex}. Falling back to 'base' model.")
                self.model_name = "base"
                self.model = WhisperModel(
                    "base",
                    device=self.device,
                    compute_type=self.compute_type,
                    cpu_threads=4,
                )
                self.engine = "faster-whisper"

        return self.model

    def transcribe(
        self,
        file_path: str,
        language: str | None = None,
    ) -> dict[str, Any]:
        """
        Transcribe audio/video file with word-level timestamps.
        """
        model = self._load_model()
        lang_param = None if (not language or language in ("auto", "mixed")) else language

        if self.engine == "whisperx" and HAS_WHISPERX:
            return self._transcribe_whisperx(file_path, lang_param)
        else:
            return self._transcribe_faster_whisper(model, file_path, lang_param)

    def _transcribe_faster_whisper(
        self,
        model: WhisperModel,
        file_path: str,
        language: str | None,
    ) -> dict[str, Any]:
        # ── Step 1: Auto-detect language (no prompt — avoids biased detection) ──
        detected_language = language
        if not language:
            _, info = model.transcribe(
                file_path,
                language=None,
                beam_size=5,
                temperature=0,
                vad_filter=True,
                vad_parameters=dict(min_silence_duration_ms=500),
                condition_on_previous_text=False,
            )
            detected_language = info.language or "unknown"
            logger.info(
                f"Auto-detected language: {detected_language} "
                f"(probability={info.language_probability:.2f})"
            )

        # ── Step 2: No initial_prompt needed ────────────────────────────────────
        # The 'small' model (default) outputs Bengali/other scripts natively when
        # language is set. Short prompts (e.g. "বাংলা।") cause the base model
        # to produce hallucination loops, so no prompt is the safe approach.

        # ── Step 3: Full transcription with anti-hallucination guards ──────────
        segments_gen, _ = model.transcribe(
            file_path,
            language=detected_language if detected_language != "unknown" else None,
            word_timestamps=True,
            vad_filter=True,
            vad_parameters=dict(min_silence_duration_ms=500),
            beam_size=5,
            best_of=5,
            patience=1.0,
            repetition_penalty=1.05,
            # ── Anti-hallucination ───────────────────────────────────────────
            temperature=0,                    # deterministic — no random sampling
            condition_on_previous_text=False, # prevents cascading hallucination
            no_speech_threshold=0.6,          # drop silent / non-speech windows
            compression_ratio_threshold=2.4,  # default — avoids false positives
            log_prob_threshold=-1.0,          # drop low-confidence segments
        )

        formatted_segments = []

        for seg in segments_gen:
            text = seg.text.strip()
            if not text:
                continue

            # ── Post-processing: drop hallucinated / stuck-loop segments ──────
            if _is_hallucinated(text):
                logger.warning(f"Dropped hallucinated segment [{seg.start:.1f}s]: {text[:60]!r}")
                continue

            words = []
            if seg.words:
                for w in seg.words:
                    word_text = w.word.strip()
                    if not word_text:
                        continue
                    words.append({
                        "word": word_text,
                        "start": round(w.start, 3),
                        "end": round(w.end, 3),
                        "score": round(w.probability, 3) if hasattr(w, "probability") else 1.0,
                    })
            else:
                words.append({
                    "word": text,
                    "start": round(seg.start, 3),
                    "end": round(seg.end, 3),
                    "score": 1.0,
                })

            if not words:
                continue

            formatted_segments.append({
                "start": round(seg.start, 3),
                "end": round(seg.end, 3),
                "text": text,
                "words": words,
            })

        return {
            "language": detected_language,
            "segments": formatted_segments,
        }

    def _transcribe_whisperx(
        self,
        file_path: str,
        language: str | None,
    ) -> dict[str, Any]:
        audio = whisperx.load_audio(file_path)
        result = self.model.transcribe(audio, language=language, batch_size=8)
        detected_language = result.get("language") or language or "unknown"

        # Attempt alignment
        try:
            model_a, metadata = whisperx.load_align_model(
                language_code=detected_language,
                device=self.device,
            )
            aligned = whisperx.align(
                result["segments"],
                model_a,
                metadata,
                audio,
                self.device,
                return_char_alignments=False,
            )
            return {
                "language": detected_language,
                "segments": aligned.get("segments", []),
            }
        except Exception as ex:
            logger.warning(f"WhisperX alignment skipped: {ex}")
            return {
                "language": detected_language,
                "segments": result.get("segments", []),
            }