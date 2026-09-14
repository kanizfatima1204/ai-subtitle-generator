class SubtitleSegmenter:

    MAX_CHARS = 42
    MAX_LINES = 2

    MIN_DURATION = 1.0
    MAX_DURATION = 7.0

    def create_subtitles(self, segments):

        subtitles = []

        for segment in segments:

            words = segment.get("words", [])

            if not words:
                subtitles.append({
                    "start": segment["start"],
                    "end": segment["end"],
                    "text": segment["text"]
                })

                continue

            current_words = []
            current_length = 0

            for word in words:

                word_text = word["word"]

                extra_length = (
                    len(word_text)
                    + (1 if current_words else 0)
                )

                if (
                    current_length + extra_length
                    > self.MAX_CHARS
                    and current_words
                ):

                    subtitles.append(
                        self.build_subtitle(
                            current_words
                        )
                    )

                    current_words = []
                    current_length = 0

                current_words.append(word)

                current_length += extra_length

            if current_words:

                subtitles.append(
                    self.build_subtitle(
                        current_words
                    )
                )

        return subtitles

    def build_subtitle(self, words):

        start = words[0]["start"]

        end = words[-1]["end"]

        text = " ".join(
            word["word"]
            for word in words
        ).strip()

        duration = end - start

        if duration < self.MIN_DURATION:
            end = start + self.MIN_DURATION

        if duration > self.MAX_DURATION:
            end = start + self.MAX_DURATION

        return {
            "start": round(start, 3),
            "end": round(end, 3),
            "text": text
        }