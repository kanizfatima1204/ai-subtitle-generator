<?php

namespace App\Services;

class SubtitleService
{
    /**
     * Maximum characters allowed in one subtitle line.
     */
    protected int $maxCharactersPerLine = 42;

    /**
     * Maximum number of lines per subtitle.
     */
    protected int $maxLines = 2;

    /**
     * Minimum subtitle duration in seconds.
     */
    protected float $minDuration = 1.0;

    /**
     * Maximum subtitle duration in seconds.
     */
    protected float $maxDuration = 7.0;

    /**
     * Generate complete SRT content.
     */
    public function generateSrt(array $segments): string
    {
        $srt = '';
        $sequence = 1;

        foreach ($segments as $segment) {
            $start = (float) ($segment['start'] ?? 0);
            $end = (float) ($segment['end'] ?? 0);
            $text = trim((string) ($segment['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            if ($end <= $start) {
                $end = $start + $this->minDuration;
            }

            $text = $this->cleanText($text);

            $srt .= $sequence.PHP_EOL;
            $srt .= $this->formatTimestamp($start);
            $srt .= ' --> ';
            $srt .= $this->formatTimestamp($end);
            $srt .= PHP_EOL;

            $srt .= $this->formatSubtitleText($text);
            $srt .= PHP_EOL.PHP_EOL;

            $sequence++;
        }

        return $srt;
    }

    /**
     * Process raw AI segments into subtitle-ready segments.
     *
     * This method is useful if Laravel receives raw Whisper
     * segments instead of already segmented subtitles.
     */
    public function processSegments(array $segments): array
    {
        $result = [];

        foreach ($segments as $segment) {
            $text = $this->cleanText(
                (string) ($segment['text'] ?? '')
            );

            if ($text === '') {
                continue;
            }

            $start = max(
                0,
                (float) ($segment['start'] ?? 0)
            );

            $end = max(
                $start + $this->minDuration,
                (float) ($segment['end'] ?? ($start + 1))
            );

            /*
             * If the segment already contains word timestamps,
             * use them to split without losing text.
             */
            if (
                isset($segment['words']) &&
                is_array($segment['words']) &&
                count($segment['words']) > 0
            ) {
                $wordSegments = $this->buildFromWords(
                    $segment['words']
                );

                foreach ($wordSegments as $wordSegment) {
                    $result[] = $wordSegment;
                }

                continue;
            }

            /*
             * Otherwise split the text naturally.
             */
            $chunks = $this->splitText($text);

            if (count($chunks) <= 1) {
                $result[] = $this->normalizeTiming(
                    $start,
                    $end,
                    $text
                );

                continue;
            }

            $durations = $this->distributeTiming(
                $start,
                $end,
                $chunks
            );

            foreach ($chunks as $index => $chunk) {
                $result[] = [
                    'start' => $durations[$index]['start'],
                    'end' => $durations[$index]['end'],
                    'text' => $chunk,
                ];
            }
        }

        return $this->preventOverlaps($result);
    }

    /**
     * Build subtitle segments from word timestamps.
     */
    protected function buildFromWords(array $words): array
    {
        $subtitles = [];
        $currentWords = [];
        $currentCharacters = 0;

        foreach ($words as $word) {
            $wordText = trim(
                (string) ($word['word'] ?? '')
            );

            if ($wordText === '') {
                continue;
            }

            $wordStart = (float) ($word['start'] ?? 0);
            $wordEnd = (float) ($word['end'] ?? $wordStart);

            $additionalCharacters =
                strlen($wordText) +
                (count($currentWords) > 0 ? 1 : 0);

            /*
             * Check whether adding this word would make
             * the subtitle too large.
             */
            if (
                $currentWords &&
                $this->wouldExceedSubtitleLimit(
                    $currentWords,
                    $wordText,
                    $currentCharacters
                )
            ) {
                $subtitle = $this->createWordSubtitle(
                    $currentWords
                );

                if ($subtitle !== null) {
                    $subtitles[] = $subtitle;
                }

                $currentWords = [];
                $currentCharacters = 0;
            }

            $currentWords[] = [
                'start' => $wordStart,
                'end' => $wordEnd,
                'word' => $wordText,
            ];

            $currentCharacters += $additionalCharacters;

            /*
             * Break naturally at punctuation if the subtitle
             * already has a reasonable amount of text.
             */
            if (
                $this->endsWithSentencePunctuation($wordText) &&
                $currentCharacters >= 15
            ) {
                $subtitle = $this->createWordSubtitle(
                    $currentWords
                );

                if ($subtitle !== null) {
                    $subtitles[] = $subtitle;
                }

                $currentWords = [];
                $currentCharacters = 0;
            }
        }

        if ($currentWords) {
            $subtitle = $this->createWordSubtitle(
                $currentWords
            );

            if ($subtitle !== null) {
                $subtitles[] = $subtitle;
            }
        }

        return $subtitles;
    }

    /**
     * Determine whether adding another word would exceed
     * the two-line subtitle capacity.
     */
    protected function wouldExceedSubtitleLimit(
        array $currentWords,
        string $newWord,
        int $currentCharacters
    ): bool {
        $candidateWords = array_merge(
            $currentWords,
            [[
                'word' => $newWord,
            ]]
        );

        $text = trim(
            implode(
                ' ',
                array_map(
                    fn ($word) => $word['word'],
                    $candidateWords
                )
            )
        );

        return ! $this->fitsInSubtitle($text);
    }

    /**
     * Check whether text fits into maximum lines.
     */
    protected function fitsInSubtitle(string $text): bool
    {
        $lines = $this->wrapText($text);

        return count($lines) <= $this->maxLines;
    }

    /**
     * Create a subtitle from word timestamps.
     */
    protected function createWordSubtitle(
        array $words
    ): ?array {
        if (empty($words)) {
            return null;
        }

        $start = (float) $words[0]['start'];

        $end = (float) end($words)['end'];

        $text = trim(
            implode(
                ' ',
                array_map(
                    fn ($word) => $word['word'],
                    $words
                )
            )
        );

        $text = $this->cleanText($text);

        if ($text === '') {
            return null;
        }

        return $this->normalizeTiming(
            $start,
            $end,
            $text
        );
    }

    /**
     * Normalize subtitle timing.
     */
    protected function normalizeTiming(
        float $start,
        float $end,
        string $text
    ): array {
        $duration = $end - $start;

        if ($duration < $this->minDuration) {
            $end = $start + $this->minDuration;
        }

        if ($duration > $this->maxDuration) {
            $end = $start + $this->maxDuration;
        }

        return [
            'start' => round($start, 3),
            'end' => round($end, 3),
            'text' => $this->cleanText($text),
        ];
    }

    /**
     * Split long text into natural chunks.
     *
     * Sentence punctuation is preferred.
     */
    protected function splitText(string $text): array
    {
        $text = $this->cleanText($text);

        if ($text === '') {
            return [];
        }

        /*
         * First split by sentence-ending punctuation.
         *
         * Supports English and Bangla punctuation.
         */
        $sentences = preg_split(
            '/(?<=[।!?])\s+/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (! $sentences) {
            return [$text];
        }

        $chunks = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if ($sentence === '') {
                continue;
            }

            /*
             * If sentence already fits, keep it.
             */
            if ($this->fitsInSubtitle($sentence)) {
                $chunks[] = $sentence;

                continue;
            }

            /*
             * Otherwise split by words without losing anything.
             */
            $words = preg_split(
                '/\s+/u',
                $sentence,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            $current = '';

            foreach ($words as $word) {
                $candidate = trim(
                    $current === ''
                        ? $word
                        : $current.' '.$word
                );

                if (
                    $current !== '' &&
                    ! $this->fitsInSubtitle($candidate)
                ) {
                    $chunks[] = $current;
                    $current = $word;
                } else {
                    $current = $candidate;
                }
            }

            if ($current !== '') {
                $chunks[] = $current;
            }
        }

        return $chunks;
    }

    /**
     * Distribute segment duration proportionally based
     * on character count.
     */
    protected function distributeTiming(
        float $start,
        float $end,
        array $chunks
    ): array {
        $totalDuration = max(
            0.001,
            $end - $start
        );

        $weights = array_map(
            fn ($chunk) => max(
                1,
                mb_strlen($chunk)
            ),
            $chunks
        );

        $totalWeight = array_sum($weights);

        $result = [];
        $currentStart = $start;

        foreach ($chunks as $index => $chunk) {
            $ratio =
                $weights[$index] /
                $totalWeight;

            $duration =
                $totalDuration * $ratio;

            $currentEnd =
                $index === count($chunks) - 1
                    ? $end
                    : $currentStart + $duration;

            $result[] = [
                'start' => round(
                    $currentStart,
                    3
                ),
                'end' => round(
                    $currentEnd,
                    3
                ),
                'text' => $chunk,
            ];

            $currentStart = $currentEnd;
        }

        return $result;
    }

    /**
     * Prevent accidental overlapping subtitle ranges.
     */
    protected function preventOverlaps(
        array $segments
    ): array {
        usort(
            $segments,
            fn ($a, $b) => $a['start'] <=> $b['start']
        );

        $result = [];
        $previousEnd = 0;

        foreach ($segments as $segment) {
            $start = max(
                $previousEnd,
                (float) $segment['start']
            );

            $end = max(
                $start + $this->minDuration,
                (float) $segment['end']
            );

            if (
                $end - $start >
                $this->maxDuration
            ) {
                $end =
                    $start +
                    $this->maxDuration;
            }

            $result[] = [
                'start' => round($start, 3),
                'end' => round($end, 3),
                'text' => $this->cleanText(
                    $segment['text']
                ),
            ];

            $previousEnd = $end;
        }

        return $result;
    }

    /**
     * Clean subtitle text.
     */
    protected function cleanText(string $text): string
    {
        $text = trim($text);

        /*
         * Normalize whitespace.
         */
        $text = preg_replace(
            '/\s+/u',
            ' ',
            $text
        );

        /*
         * Normalize common Bangla punctuation.
         */
        $text = str_replace(
            ['৷', '|'],
            '।',
            $text
        );

        /*
         * Remove spaces before punctuation.
         */
        $text = preg_replace(
            '/\s+([,।!?;:])/u',
            '$1',
            $text
        );

        /*
         * Normalize repeated punctuation.
         */
        $text = preg_replace(
            '/([!?।]){2,}/u',
            '$1',
            $text
        );

        /*
         * Add missing space after English punctuation
         * when followed by an English/Banglish character.
         */
        $text = preg_replace(
            '/([,:;!?])(?=[A-Za-z])/u',
            '$1 ',
            $text
        );

        return trim($text);
    }

    /**
     * Format subtitle into maximum two lines.
     *
     * IMPORTANT:
     * This method never truncates text.
     */
    protected function formatSubtitleText(
        string $text
    ): string {
        $lines = $this->wrapText($text);

        return implode(
            PHP_EOL,
            $lines
        );
    }

    /**
     * Wrap text intelligently.
     */
    protected function wrapText(string $text): array
    {
        $text = $this->cleanText($text);

        if ($text === '') {
            return [];
        }

        $words = preg_split(
            '/\s+/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            /*
             * Handle an unusually long single word.
             */
            if (
                mb_strlen($word) >
                $this->maxCharactersPerLine
            ) {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                    $currentLine = '';
                }

                $chunks = mb_str_split(
                    $word,
                    $this->maxCharactersPerLine
                );

                foreach ($chunks as $chunk) {
                    $lines[] = $chunk;
                }

                continue;
            }

            $candidate =
                $currentLine === ''
                    ? $word
                    : $currentLine.' '.$word;

            if (
                mb_strlen($candidate) <=
                $this->maxCharactersPerLine
            ) {
                $currentLine = $candidate;

                continue;
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }

            $currentLine = $word;
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        /*
         * Balance two lines where possible.
         */
        if (count($lines) > 2) {
            $lines = $this->rebalanceLines(
                $words
            );
        }

        return $lines;
    }

    /**
     * Attempt to create a balanced two-line subtitle.
     */
    protected function rebalanceLines(
        array $words
    ): array {
        $text = implode(' ', $words);

        $bestFirstLine = null;
        $bestDifference = PHP_INT_MAX;

        $count = count($words);

        for ($i = 1; $i < $count; $i++) {
            $first = implode(
                ' ',
                array_slice(
                    $words,
                    0,
                    $i
                )
            );

            $second = implode(
                ' ',
                array_slice(
                    $words,
                    $i
                )
            );

            if (
                mb_strlen($first) >
                $this->maxCharactersPerLine
            ) {
                continue;
            }

            if (
                mb_strlen($second) >
                $this->maxCharactersPerLine
            ) {
                continue;
            }

            $difference = abs(
                mb_strlen($first) -
                mb_strlen($second)
            );

            if ($difference < $bestDifference) {
                $bestDifference = $difference;

                $bestFirstLine = [
                    $first,
                    $second,
                ];
            }
        }

        /*
         * If it cannot fit into two lines,
         * return natural lines rather than deleting text.
         */
        if ($bestFirstLine === null) {
            return $this->naturalWrap(
                $text
            );
        }

        return $bestFirstLine;
    }

    /**
     * Natural wrapping fallback.
     */
    protected function naturalWrap(
        string $text
    ): array {
        $words = preg_split(
            '/\s+/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate =
                $current === ''
                    ? $word
                    : $current.' '.$word;

            if (
                $current !== '' &&
                mb_strlen($candidate) >
                $this->maxCharactersPerLine
            ) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Check sentence punctuation.
     */
    protected function endsWithSentencePunctuation(
        string $text
    ): bool {
        return (bool) preg_match(
            '/[।!?]$/u',
            trim($text)
        );
    }

    /**
     * Convert seconds to SRT timestamp.
     */
    protected function formatTimestamp(
        float $seconds
    ): string {
        $seconds = max(
            0,
            $seconds
        );

        $hours = floor(
            $seconds / 3600
        );

        $minutes = floor(
            ($seconds % 3600) / 60
        );

        $remainingSeconds =
            $seconds % 60;

        $wholeSeconds = floor(
            $remainingSeconds
        );

        $milliseconds = round(
            (
                $remainingSeconds -
                $wholeSeconds
            ) * 1000
        );

        /*
         * Handle rounding to exactly 1000ms.
         */
        if ($milliseconds >= 1000) {
            $milliseconds = 0;
            $wholeSeconds++;

            if ($wholeSeconds >= 60) {
                $wholeSeconds = 0;
                $minutes++;
            }

            if ($minutes >= 60) {
                $minutes = 0;
                $hours++;
            }
        }

        return sprintf(
            '%02d:%02d:%02d,%03d',
            $hours,
            $minutes,
            $wholeSeconds,
            $milliseconds
        );
    }
}
