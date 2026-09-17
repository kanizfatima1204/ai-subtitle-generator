<?php

namespace App\Jobs;

use App\Models\SubtitleJob;
use App\Services\SubtitleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessSubtitleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum execution time.
     */
    public int $timeout = 3600;

    /**
     * Number of attempts.
     */
    public int $tries = 2;

    /**
     * Subtitle job ID.
     */
    public SubtitleJob $subtitleJob;

    /**
     * Create a new job instance.
     */
    public function __construct(SubtitleJob $subtitleJob)
    {
        $this->subtitleJob = $subtitleJob;
    }

    /**
     * Execute the job.
     */
    public function handle(
        SubtitleService $subtitleService
    ): void {
        $job = $this->subtitleJob;

        try {
            /*
            |--------------------------------------------------------------------------
            | 1. Mark job as processing
            |--------------------------------------------------------------------------
            */

            $job->update([
                'status' => 'processing',
                'started_at' => now(),
                'error_message' => null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 2. Get uploaded file path
            |--------------------------------------------------------------------------
            */

            $filePath = Storage::disk('public')
                ->path($job->file_path);

            if (! file_exists($filePath)) {
                throw new \RuntimeException(
                    'Uploaded media file was not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Get AI service URL
            |--------------------------------------------------------------------------
            */

            $aiServiceUrl = rtrim(
                config('services.ai.url'),
                '/'
            );

            if (! $aiServiceUrl) {
                throw new \RuntimeException(
                    'AI service URL is not configured.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Prepare language
            |--------------------------------------------------------------------------
            |
            | auto = let Whisper detect language.
            |
            */

            $language = $job->language;

            if (
                empty($language) ||
                $language === 'auto'
            ) {
                $language = null;
            }

            /*
            |--------------------------------------------------------------------------
            | 5. Send media to Python AI service
            |--------------------------------------------------------------------------
            */

            /*
             |--------------------------------------------------------------------------
             | AI Request
             |--------------------------------------------------------------------------
             */

            $response = Http::timeout(3600)
                ->connectTimeout(120)
                ->attach(
                    'file',
                    fopen($filePath, 'r'),
                    $job->original_filename
                )
                ->post(
                    $aiServiceUrl.'/transcribe',
                    array_filter([
                        'language' => $language,
                        'model' => $job->model ?? 'medium',
                    ], fn ($value) => $value !== null)
                );

            /*
            |--------------------------------------------------------------------------
            | 6. Check AI response
            |--------------------------------------------------------------------------
            */

            $response->throw();

            $result = $response->json();

            if (! is_array($result)) {
                throw new \RuntimeException(
                    'Invalid response received from AI service.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 7. Get subtitles from AI response
            |--------------------------------------------------------------------------
            |
            | Python service returns:
            |
            | {
            |     "language": "en",
            |     "segments": [...],
            |     "subtitles": [...]
            | }
            |
            */

            $subtitles = $result['subtitles']
                ?? $result['segments']
                ?? [];

            if (! is_array($subtitles)) {
                $subtitles = [];
            }

            /*
            |--------------------------------------------------------------------------
            | 8. Normalize subtitle data
            |--------------------------------------------------------------------------
            */

            $normalizedSubtitles = [];

            foreach ($subtitles as $subtitle) {
                $start = $subtitle['start']
                    ?? $subtitle['start_time']
                    ?? null;

                $end = $subtitle['end']
                    ?? $subtitle['end_time']
                    ?? null;

                $text = $subtitle['text']
                    ?? '';

                if (
                    $start === null ||
                    $end === null
                ) {
                    continue;
                }

                $start = (float) $start;
                $end = (float) $end;

                $text = trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        (string) $text
                    )
                );

                /*
                |--------------------------------------------------------------------------
                | Skip invalid subtitle
                |--------------------------------------------------------------------------
                */

                if ($text === '') {
                    continue;
                }

                if ($start < 0) {
                    $start = 0;
                }

                if ($end <= $start) {
                    $end = $start + 1;
                }

                $normalizedSubtitles[] = [
                    'start' => round($start, 3),
                    'end' => round($end, 3),
                    'text' => $text,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | 9. Make sure subtitles exist
            |--------------------------------------------------------------------------
            */

            if (empty($normalizedSubtitles)) {
                throw new \RuntimeException(
                    'AI service did not generate any subtitles.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 10. Delete old subtitle segments
            |--------------------------------------------------------------------------
            |
            | Important for retry/reprocessing.
            |
            */

            $job->segments()->delete();

            /*
            |--------------------------------------------------------------------------
            | 11. Save subtitle segments into database
            |--------------------------------------------------------------------------
            */

            foreach (
                $normalizedSubtitles as $index => $subtitle
            ) {
                $job->segments()->create([
                    'sequence' => $index + 1,
                    'start_time' => $subtitle['start'],
                    'end_time' => $subtitle['end'],
                    'text' => $subtitle['text'],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 12. Read saved segments
            |--------------------------------------------------------------------------
            */

            $segments = $job
                ->segments()
                ->orderBy('sequence')
                ->get()
                ->map(function ($segment) {
                    return [
                        'start' => (float) $segment->start_time,
                        'end' => (float) $segment->end_time,
                        'text' => $segment->text,
                    ];
                })
                ->toArray();

            /*
            |--------------------------------------------------------------------------
            | 13. Generate SRT
            |--------------------------------------------------------------------------
            */

            $srt = $subtitleService->generateSrt(
                $segments
            );

            /*
            |--------------------------------------------------------------------------
            | 14. Save SRT file
            |--------------------------------------------------------------------------
            */

            $srtPath =
                'subtitles/'.$job->id.'.srt';

            Storage::disk('public')->put(
                $srtPath,
                $srt
            );

            /*
            |--------------------------------------------------------------------------
            | 15. Save complete result
            |--------------------------------------------------------------------------
            */

            $job->update([
                'status' => 'completed',

                'language' => $result['language']
                    ?? $language,

                'transcript' => $result,

                'srt_path' => $srtPath,

                'completed_at' => now(),

                'error_message' => null,
            ]);
        } catch (Throwable $exception) {

            /*
            |--------------------------------------------------------------------------
            | 16. Mark job as failed
            |--------------------------------------------------------------------------
            */

            $job->update([
                'status' => 'failed',

                'error_message' => $exception->getMessage(),

                'completed_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 17. Log exception through queue system
            |--------------------------------------------------------------------------
            */

            throw $exception;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(
        ?Throwable $exception
    ): void {
        $this->subtitleJob->update([
            'status' => 'failed',

            'error_message' => $exception?->getMessage()
                ?? 'Subtitle processing failed.',

            'completed_at' => now(),
        ]);
    }
}
