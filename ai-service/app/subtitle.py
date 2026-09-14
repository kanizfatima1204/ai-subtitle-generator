from __future__ import annotations

import re
from dataclasses import dataclass
from typing import Any


MAX_CHARS = 42
MAX_LINES = 2

MIN_DURATION = 1.0
MAX_DURATION = 7.0

MIN_CPS = 8.0
TARGET_CPS = 14.0
MAX_CPS = 20.0


@dataclass
class Word:
    text: str
    start: float
    end: float


@dataclass
class Subtitle:
    start: float
    end: float
    text: str


class ProfessionalSubtitleProcessor:

    def __init__(
        self,
        max_chars: int = MAX_CHARS,
        max_lines: int = MAX_LINES,
    ):
        self.max_chars = max_chars
        self.max_lines = max_lines

    # ---------------------------------------------------------
    # Public API
    # ---------------------------------------------------------

    def create_subtitles(
        self,
        segments: list[dict[str, Any]],
    ) -> list[dict[str, Any]]:

        words = self.extract_words(
            segments
        )

        if not words:
            return []

        groups = self.group_words(words)

        subtitles = []

        for group in groups:
            subtitle = self.create_subtitle(
                group
            )

            if subtitle:
                subtitles.append(
                    subtitle
                )

        subtitles = (
            self.optimize_timings(
                subtitles
            )
        )

        subtitles = (
            self.normalize_punctuation(
                subtitles
            )
        )

        return [
            {
                "start": round(
                    item.start,
                    3
                ),
                "end": round(
                    item.end,
                    3
                ),
                "text": item.text,
            }
            for item in subtitles
        ]

    # ---------------------------------------------------------
    # Word Extraction
    # ---------------------------------------------------------

    def extract_words(
        self,
        segments: list[dict[str, Any]],
    ) -> list[Word]:

        words: list[Word] = []

        for segment in segments:

            segment_words = (
                segment.get(
                    "words",
                    []
                )
            )

            if segment_words:

                for item in segment_words:

                    text = (
                        item.get("word")
                        or item.get("text")
                        or ""
                    ).strip()

                    start = item.get(
                        "start"
                    )

                    end = item.get(
                        "end"
                    )

                    if (
                        not text
                        or start is None
                        or end is None
                    ):
                        continue

                    start = float(start)
                    end = float(end)

                    if end <= start:
                        continue

                    words.append(
                        Word(
                            text=text,
                            start=start,
                            end=end,
                        )
                    )

            else:

                text = (
                    segment.get(
                        "text",
                        ""
                    )
                    .strip()
                )

                start = segment.get(
                    "start"
                )

                end = segment.get(
                    "end"
                )

                if (
                    not text
                    or start is None
                    or end is None
                ):
                    continue

                split_words = (
                    text.split()
                )

                duration = (
                    float(end)
                    - float(start)
                )

                word_duration = (
                    duration /
                    max(
                        len(split_words),
                        1
                    )
                )

                for index, word in enumerate(
                    split_words
                ):

                    word_start = (
                        float(start)
                        +
                        index *
                        word_duration
                    )

                    word_end = (
                        word_start
                        +
                        word_duration
                    )

                    words.append(
                        Word(
                            text=word,
                            start=word_start,
                            end=word_end,
                        )
                    )

        return words

    # ---------------------------------------------------------
    # Intelligent Grouping
    # ---------------------------------------------------------

    def group_words(
        self,
        words: list[Word],
    ) -> list[list[Word]]:

        groups: list[list[Word]] = []

        current: list[Word] = []

        for word in words:

            if not current:
                current.append(word)
                continue

            candidate = (
                current +
                [word]
            )

            candidate_text = (
                self.words_to_text(
                    candidate
                )
            )

            duration = (
                word.end
                -
                current[0].start
            )

            should_break = False

            # Hard character limit.
            if self.exceeds_layout_limit(
                candidate_text
            ):
                should_break = True

            # Maximum duration.
            elif duration > MAX_DURATION:
                should_break = True

            # Strong punctuation boundary.
            elif self.is_sentence_boundary(
                current[-1].text
            ):
                should_break = True

            # Reading speed.
            elif (
                duration > 0
                and len(candidate_text)
                / duration
                > MAX_CPS
            ):
                should_break = True

            if should_break:

                if current:
                    groups.append(
                        current
                    )

                current = [word]

            else:
                current.append(word)

        if current:
            groups.append(current)

        return self.balance_groups(
            groups
        )

    # ---------------------------------------------------------
    # Group Balancing
    # ---------------------------------------------------------

    def balance_groups(
        self,
        groups: list[list[Word]],
    ) -> list[list[Word]]:

        result: list[list[Word]] = []

        for group in groups:

            if len(group) <= 1:
                result.append(group)
                continue

            text = self.words_to_text(
                group
            )

            if not self.exceeds_layout_limit(
                text
            ):
                result.append(group)
                continue

            current: list[Word] = []

            for word in group:

                candidate = (
                    current +
                    [word]
                )

                candidate_text = (
                    self.words_to_text(
                        candidate
                    )
                )

                if (
                    current
                    and
                    self.exceeds_layout_limit(
                        candidate_text
                    )
                ):
                    result.append(
                        current
                    )

                    current = [word]

                else:
                    current.append(word)

            if current:
                result.append(
                    current
                )

        return result

    # ---------------------------------------------------------
    # Subtitle Creation
    # ---------------------------------------------------------

    def create_subtitle(
        self,
        words: list[Word],
    ) -> Subtitle | None:

        if not words:
            return None

        text = self.words_to_text(
            words
        )

        text = self.clean_text(
            text
        )

        if not text:
            return None

        start = words[0].start
        end = words[-1].end

        return Subtitle(
            start=start,
            end=end,
            text=text,
        )

    # ---------------------------------------------------------
    # Text
    # ---------------------------------------------------------

    def words_to_text(
        self,
        words: list[Word],
    ) -> str:

        text = ""

        for word in words:

            if not text:
                text = word.text
                continue

            # Bangla punctuation / Latin punctuation.
            if word.text in {
                ".",
                ",",
                "!",
                "?",
                ":",
                ";",
                "।",
                "॥",
                "%",
                "…",
            }:
                text += word.text
            else:
                text += " " + word.text

        return text.strip()

    def clean_text(
        self,
        text: str,
    ) -> str:

        text = re.sub(
            r"\s+",
            " ",
            text
        )

        text = re.sub(
            r"\s+([,.!?;:।])",
            r"\1",
            text
        )

        return text.strip()

    # ---------------------------------------------------------
    # Layout
    # ---------------------------------------------------------

    def exceeds_layout_limit(
        self,
        text: str,
    ) -> bool:

        if len(text) <= self.max_chars:
            return False

        lines = self.make_two_lines(
            text
        )

        return (
            len(lines) > self.max_lines
            or
            any(
                len(line)
                > self.max_chars
                for line in lines
            )
        )

    def make_two_lines(
        self,
        text: str,
    ) -> list[str]:

        words = text.split()

        if not words:
            return []

        lines = []
        current = ""

        for word in words:

            candidate = (
                word
                if not current
                else current + " " + word
            )

            if (
                len(candidate)
                <= self.max_chars
            ):
                current = candidate

            else:

                if current:
                    lines.append(
                        current
                    )

                current = word

        if current:
            lines.append(
                current
            )

        # Balance adjacent lines.
        if len(lines) > 2:

            midpoint = len(words) // 2

            first = " ".join(
                words[:midpoint]
            )

            second = " ".join(
                words[midpoint:]
            )

            if (
                len(first)
                <= self.max_chars
                and
                len(second)
                <= self.max_chars
            ):
                lines = [
                    first,
                    second,
                ]

        return lines

    # ---------------------------------------------------------
    # Punctuation
    # ---------------------------------------------------------

    def is_sentence_boundary(
        self,
        word: str,
    ) -> bool:

        return bool(
            re.search(
                r"[.!?।]$",
                word
            )
        )

    def normalize_punctuation(
        self,
        subtitles: list[Subtitle],
    ) -> list[Subtitle]:

        for subtitle in subtitles:

            text = subtitle.text.strip()

            text = re.sub(
                r"\s+([,.!?।])",
                r"\1",
                text
            )

            # Remove duplicate punctuation.
            text = re.sub(
                r"([!?।]){2,}",
                r"\1",
                text
            )

            subtitle.text = text

        return subtitles

    # ---------------------------------------------------------
    # Timing Optimization
    # ---------------------------------------------------------

    def optimize_timings(
        self,
        subtitles: list[Subtitle],
    ) -> list[Subtitle]:

        if not subtitles:
            return []

        for index, subtitle in enumerate(
            subtitles
        ):

            previous_end = (
                subtitles[index - 1].end
                if index > 0
                else 0
            )

            next_start = (
                subtitles[index + 1].start
                if index + 1 < len(subtitles)
                else None
            )

            text_length = len(
                subtitle.text
            )

            ideal_duration = (
                text_length /
                TARGET_CPS
            )

            ideal_duration = max(
                MIN_DURATION,
                min(
                    ideal_duration,
                    MAX_DURATION
                )
            )

            original_duration = (
                subtitle.end
                -
                subtitle.start
            )

            duration = max(
                original_duration,
                ideal_duration
            )

            # Do not start before previous subtitle.
            start = max(
                subtitle.start,
                previous_end
            )

            end = (
                start +
                duration
            )

            # Do not overlap next subtitle.
            if next_start is not None:

                available = (
                    next_start -
                    start
                )

                if available >= MIN_DURATION:

                    end = min(
                        end,
                        next_start
                    )

            # Ensure minimum duration.
            if end - start < MIN_DURATION:

                end = (
                    start +
                    MIN_DURATION
                )

                if (
                    next_start is not None
                    and
                    end > next_start
                ):
                    end = next_start

            subtitle.start = round(
                start,
                3
            )

            subtitle.end = round(
                end,
                3
            )

        return subtitles