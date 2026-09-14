// resources/js/utils/subtitleQuality.js

export const MAX_CHARS = 42;
export const MAX_LINES = 2;

export const MIN_DURATION = 1;
export const MAX_DURATION = 7;

export const MIN_CPS = 8;
export const TARGET_CPS = 14;
export const MAX_CPS = 20;

/**
 * Normalize subtitle text.
 */
export function normalizeSubtitleText(text = '') {
    return String(text)
        .replace(/\s+/gu, ' ')
        .replace(/\s+([,.!?;:।])/gu, '$1')
        .replace(/([!?।]){2,}/gu, '$1')
        .trim();
}

/**
 * Count characters.
 */
export function calculateCharacters(text = '') {
    return normalizeSubtitleText(text).length;
}

/**
 * Count words.
 */
export function calculateWords(text = '') {
    const normalized = normalizeSubtitleText(text);

    if (!normalized) {
        return 0;
    }

    return normalized.split(/\s+/u).length;
}

/**
 * Calculate subtitle duration.
 */
export function calculateDuration(start, end) {
    const value =
        Number(end || 0) -
        Number(start || 0);

    return Math.max(0, value);
}

/**
 * Calculate Characters Per Second.
 */
export function calculateCPS(
    text = '',
    start = 0,
    end = 0
) {
    const duration = calculateDuration(
        start,
        end
    );

    if (duration <= 0) {
        return 999;
    }

    return (
        calculateCharacters(text) /
        duration
    );
}

/**
 * Create maximum two subtitle lines.
 *
 * Attempts to balance both lines.
 */
export function splitSubtitleLines(
    text = '',
    maxChars = MAX_CHARS
) {
    const normalized =
        normalizeSubtitleText(text);

    if (!normalized) {
        return [];
    }

    const words =
        normalized.split(/\s+/u);

    if (words.length === 1) {
        return [
            words[0],
        ];
    }

    let bestSplit = null;
    let bestDifference = Infinity;

    for (
        let index = 1;
        index < words.length;
        index++
    ) {
        const first =
            words
                .slice(0, index)
                .join(' ');

        const second =
            words
                .slice(index)
                .join(' ');

        if (
            first.length <= maxChars &&
            second.length <= maxChars
        ) {
            const difference =
                Math.abs(
                    first.length -
                    second.length
                );

            if (
                difference <
                bestDifference
            ) {
                bestDifference =
                    difference;

                bestSplit = [
                    first,
                    second,
                ];
            }
        }
    }

    if (bestSplit) {
        return bestSplit;
    }

    // Fallback wrapping.
    const lines = [];
    let current = '';

    for (const word of words) {
        const candidate =
            current
                ? `${current} ${word}`
                : word;

        if (
            candidate.length <=
            maxChars
        ) {
            current = candidate;
        } else {
            if (current) {
                lines.push(current);
            }

            current = word;
        }
    }

    if (current) {
        lines.push(current);
    }

    return lines;
}

/**
 * Check subtitle layout.
 */
export function checkLayout(
    text = ''
) {
    const normalized =
        normalizeSubtitleText(text);

    const lines =
        splitSubtitleLines(
            normalized
        );

    const characters =
        calculateCharacters(
            normalized
        );

    const tooManyCharacters =
        characters > MAX_CHARS;

    const tooManyLines =
        lines.length > MAX_LINES;

    const longLine =
        lines.some(
            (line) =>
                line.length >
                MAX_CHARS
        );

    return {
        characters,
        lines,
        lineCount: lines.length,

        tooManyCharacters,
        tooManyLines,
        longLine,

        valid:
            !tooManyCharacters &&
            !tooManyLines &&
            !longLine,
    };
}

/**
 * Determine subtitle quality.
 */
export function calculateQuality(
    subtitle
) {
    const text =
        normalizeSubtitleText(
            subtitle?.text || ''
        );

    const start =
        Number(
            subtitle?.start_time || 0
        );

    const end =
        Number(
            subtitle?.end_time || 0
        );

    const duration =
        calculateDuration(
            start,
            end
        );

    const characters =
        calculateCharacters(
            text
        );

    const words =
        calculateWords(
            text
        );

    const cps =
        calculateCPS(
            text,
            start,
            end
        );

    const layout =
        checkLayout(text);

    const errors = [];
    const warnings = [];

    if (
        characters >
        MAX_CHARS
    ) {
        errors.push(
            `Too many characters (${characters}/${MAX_CHARS}).`
        );
    }

    if (
        layout.lineCount >
        MAX_LINES
    ) {
        errors.push(
            `Too many lines (${layout.lineCount}/${MAX_LINES}).`
        );
    }

    if (
        layout.longLine
    ) {
        errors.push(
            'One or more lines exceed 42 characters.'
        );
    }

    if (
        duration <= 0
    ) {
        errors.push(
            'Invalid subtitle timing.'
        );
    }

    if (
        cps > MAX_CPS
    ) {
        warnings.push(
            `Reading speed is high (${cps.toFixed(1)} CPS).`
        );
    }

    if (
        cps < MIN_CPS &&
        duration > 0
    ) {
        warnings.push(
            `Reading speed is low (${cps.toFixed(1)} CPS).`
        );
    }

    if (
        duration >
        MAX_DURATION
    ) {
        warnings.push(
            `Subtitle remains visible for more than ${MAX_DURATION} seconds.`
        );
    }

    let status = 'good';

    if (errors.length) {
        status = 'poor';
    } else if (warnings.length) {
        status = 'warning';
    }

    return {
        status,

        text,

        characters,
        words,

        duration,

        cps,

        lines:
            layout.lines,

        lineCount:
            layout.lineCount,

        errors,
        warnings,

        valid:
            status === 'good',
    };
}

/**
 * Find the best word boundary for splitting.
 */
function findBestSplit(
    words,
    maxChars = MAX_CHARS
) {
    if (words.length <= 1) {
        return 1;
    }

    let bestIndex = 1;
    let bestScore = Infinity;

    for (
        let index = 1;
        index < words.length;
        index++
    ) {
        const first =
            words
                .slice(0, index)
                .join(' ');

        const second =
            words
                .slice(index)
                .join(' ');

        if (
            first.length >
            maxChars ||
            second.length >
            maxChars
        ) {
            continue;
        }

        const difference =
            Math.abs(
                first.length -
                second.length
            );

        const previousWord =
            words[index - 1];

        const punctuationBonus =
            /[,.!?;:।]$/u.test(
                previousWord
            )
                ? -100
                : 0;

        const score =
            difference +
            punctuationBonus;

        if (
            score <
            bestScore
        ) {
            bestScore = score;
            bestIndex = index;
        }
    }

    return bestIndex;
}

/**
 * Split a subtitle into multiple segments.
 *
 * IMPORTANT:
 * This never throws away text.
 */
export function splitSubtitle(
    subtitle
) {
    const text =
        normalizeSubtitleText(
            subtitle?.text || ''
        );

    if (!text) {
        return [];
    }

    const words =
        text.split(/\s+/u);

    if (words.length <= 1) {
        return [
            {
                ...subtitle,
                text,
            },
        ];
    }

    const chunks = [];

    let remaining = [
        ...words,
    ];

    while (remaining.length) {
        let bestCount =
            remaining.length;

        for (
            let count =
                remaining.length;
            count >= 1;
            count--
        ) {
            const candidate =
                remaining
                    .slice(0, count)
                    .join(' ');

            if (
                !checkLayout(
                    candidate
                ).tooManyCharacters &&
                !checkLayout(
                    candidate
                ).tooManyLines
            ) {
                bestCount = count;
                break;
            }
        }

        if (
            bestCount ===
            remaining.length
        ) {
            const splitIndex =
                findBestSplit(
                    remaining
                );

            bestCount =
                Math.max(
                    1,
                    splitIndex
                );
        }

        chunks.push(
            remaining.slice(
                0,
                bestCount
            )
        );

        remaining =
            remaining.slice(
                bestCount
            );
    }

    const totalDuration =
        calculateDuration(
            subtitle.start_time,
            subtitle.end_time
        );

    const totalCharacters =
        chunks.reduce(
            (total, chunk) =>
                total +
                chunk.join(' ').length,
            0
        );

    const start =
        Number(
            subtitle.start_time
        );

    const end =
        Number(
            subtitle.end_time
        );

    let cursor = start;

    return chunks.map(
        (chunk, index) => {
            const text =
                chunk.join(' ');

            const ratio =
                totalCharacters > 0
                    ? text.length /
                    totalCharacters
                    : 1 / chunks.length;

            let chunkDuration =
                totalDuration *
                ratio;

            chunkDuration =
                Math.max(
                    MIN_DURATION,
                    chunkDuration
                );

            let chunkEnd =
                index ===
                    chunks.length - 1
                    ? end
                    : cursor +
                    chunkDuration;

            if (
                index ===
                chunks.length - 1
            ) {
                chunkEnd =
                    Math.max(
                        cursor +
                        MIN_DURATION,
                        end
                    );
            }

            return {
                ...subtitle,

                id:
                    index === 0
                        ? subtitle.id
                        : null,

                sequence:
                    subtitle.sequence +
                    index,

                start_time:
                    Number(
                        cursor.toFixed(3)
                    ),

                end_time:
                    Number(
                        chunkEnd.toFixed(3)
                    ),

                text,
            };
        }
    );
}

/**
 * Automatically extend a subtitle
 * when its reading speed is too high.
 */
export function autoFitTiming(
    subtitle,
    nextSubtitle = null
) {
    const result = {
        ...subtitle,
    };

    const text =
        normalizeSubtitleText(
            result.text
        );

    const start =
        Number(
            result.start_time
        );

    let end =
        Number(
            result.end_time
        );

    const requiredDuration =
        Math.max(
            MIN_DURATION,
            Math.min(
                MAX_DURATION,
                calculateCharacters(
                    text
                ) / TARGET_CPS
            )
        );

    let targetEnd =
        start +
        requiredDuration;

    if (nextSubtitle) {
        const nextStart =
            Number(
                nextSubtitle.start_time
            );

        if (
            targetEnd >
            nextStart
        ) {
            targetEnd =
                nextStart;
        }
    }

    if (
        targetEnd >
        end
    ) {
        end = targetEnd;
    }

    result.start_time =
        Number(
            start.toFixed(3)
        );

    result.end_time =
        Number(
            end.toFixed(3)
        );

    return result;
}

/**
 * Complete Auto Fix operation.
 */
export function autoFixSubtitle(
    subtitle,
    nextSubtitle = null
) {
    const quality =
        calculateQuality(
            subtitle
        );

    let result = [
        {
            ...subtitle,
        },
    ];

    if (
        quality.status ===
        'poor'
    ) {
        result =
            splitSubtitle(
                subtitle
            );
    }

    return result.map(
        (item, index) =>
            autoFitTiming(
                item,
                index ===
                    result.length - 1
                    ? nextSubtitle
                    : result[index + 1]
            )
    );
}

/**
 * Apply quality metadata.
 */
export function enrichSubtitle(
    subtitle
) {
    const quality =
        calculateQuality(
            subtitle
        );

    return {
        ...subtitle,

        characters:
            quality.characters,

        words:
            quality.words,

        cps:
            Number(
                quality.cps.toFixed(2)
            ),

        lines:
            quality.lines,

        quality:
            quality.status,

        quality_errors:
            quality.errors,

        quality_warnings:
            quality.warnings,
    };
}

/**
 * Process an entire subtitle list.
 */
export function enrichSubtitles(
    subtitles = []
) {
    return subtitles.map(
        enrichSubtitle
    );
}