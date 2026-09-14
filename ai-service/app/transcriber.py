import os
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
        model_name: str = "medium",
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

            logger.info(f"Loading faster-whisper model '{self.model_name}' on {self.device} ({self.compute_type})...")
            self.model = WhisperModel(
                self.model_name,
                device=self.device,
                compute_type=self.compute_type,
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
        segments_gen, info = model.transcribe(
            file_path,
            language=language,
            word_timestamps=True,
            vad_filter=True,
            vad_parameters=dict(min_silence_duration_ms=500),
            beam_size=5,
        )

        detected_language = language or info.language or "unknown"
        formatted_segments = []

        for seg in segments_gen:
            words = []
            if seg.words:
                for w in seg.words:
                    words.append({
                        "word": w.word.strip(),
                        "start": round(w.start, 3),
                        "end": round(w.end, 3),
                        "score": round(w.probability, 3) if hasattr(w, "probability") else 1.0,
                    })
            else:
                # If no word-level timestamps generated, fallback to segment text
                words.append({
                    "word": seg.text.strip(),
                    "start": round(seg.start, 3),
                    "end": round(seg.end, 3),
                    "score": 1.0,
                })

            formatted_segments.append({
                "start": round(seg.start, 3),
                "end": round(seg.end, 3),
                "text": seg.text.strip(),
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