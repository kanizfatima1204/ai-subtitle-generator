<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubtitleRequest;
use App\Http\Requests\UpdateSubtitleRequest;
use App\Jobs\ProcessSubtitleJob;
use App\Models\SubtitleJob;
use App\Services\SubtitleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Requests\AIAssistRequest;
use App\Services\AIService;
use Illuminate\Support\Facades\DB;


class SubtitleController extends Controller
{
    /**
     * Display the authenticated user's subtitle jobs.
     */
    public function index(Request $request): JsonResponse
    {
        $jobs = $request
            ->user()
            ->subtitleJobs()
            ->select([
                'id',
                'original_filename',
                'status',
                'language',
                'model',
                'file_size',
                'srt_path',
                'error_message',
                'created_at',
                'completed_at',
            ])
            ->latest()
            ->paginate(10);

        return response()->json($jobs);
    }

    /**
     * Create a new subtitle processing job.
     */
    public function store(
        StoreSubtitleRequest $request
    ): JsonResponse {
        $file = $request->file('file');

        /*
        |--------------------------------------------------------------------------
        | Store uploaded media
        |--------------------------------------------------------------------------
        */

        $path = $file->store(
            'subtitle-inputs',
            'public'
        );

        /*
        |--------------------------------------------------------------------------
        | Create database job
        |--------------------------------------------------------------------------
        */

        $subtitleJob = SubtitleJob::create([
            'user_id' => $request->user()->id,

            'original_filename' =>
                $file->getClientOriginalName(),

            'file_path' => $path,

            'status' => 'pending',

            'language' =>
                $request->validated('language')
                ?? 'auto',

            'model' =>
                $request->validated('model')
                ?? 'base',

            'file_size' =>
                $file->getSize(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Dispatch AI processing
        |--------------------------------------------------------------------------
        */

        ProcessSubtitleJob::dispatch(
            $subtitleJob
        );

        return response()->json([
            'message' =>
                'Subtitle processing started.',

            'job' => [
                'id' =>
                    $subtitleJob->id,

                'original_filename' =>
                    $subtitleJob->original_filename,

                'file_path' =>
                    $subtitleJob->file_path,

                'status' =>
                    $subtitleJob->status,

                'language' =>
                    $subtitleJob->language,

                'model' =>
                    $subtitleJob->model,

                'file_size' =>
                    $subtitleJob->file_size,

                'created_at' =>
                    $subtitleJob->created_at,
            ],
        ], 201);
    }

    /**
     * Display a specific subtitle job.
     */
    public function show(
        SubtitleJob $subtitleJob
    ): JsonResponse {
        $this->authorize(
            'view',
            $subtitleJob
        );

        return response()->json([
            'id' =>
                $subtitleJob->id,

            'original_filename' =>
                $subtitleJob->original_filename,

            'file_path' =>
                $subtitleJob->file_path,

            'status' =>
                $subtitleJob->status,

            'language' =>
                $subtitleJob->language,

            'model' =>
                $subtitleJob->model,

            'file_size' =>
                $subtitleJob->file_size,

            'srt_path' =>
                $subtitleJob->srt_path,

            'error_message' =>
                $subtitleJob->error_message,

            'created_at' =>
                $subtitleJob->created_at,

            'started_at' =>
                $subtitleJob->started_at,

            'completed_at' =>
                $subtitleJob->completed_at,
        ]);
    }

    /**
     * Preview subtitle segments.
     */
    public function preview(
        SubtitleJob $subtitleJob
    ): JsonResponse {
        $this->authorize(
            'view',
            $subtitleJob
        );

        $subtitles = $subtitleJob
            ->segments()
            ->orderBy('sequence')
            ->get()
            ->map(function ($segment) {
                return [
                    'id' =>
                        $segment->id,

                    'sequence' =>
                        $segment->sequence,

                    'start_time' =>
                        (float) $segment->start_time,

                    'end_time' =>
                        (float) $segment->end_time,

                    'text' =>
                        $segment->text,
                ];
            });

        return response()->json([
            'job_id' =>
                $subtitleJob->id,

            'subtitles' =>
                $subtitles,
        ]);
    }

    /**
     * Update subtitle segments and regenerate SRT.
     */
    public function updateSubtitles(
        UpdateSubtitleRequest $request,
        SubtitleJob $subtitleJob,
        SubtitleService $subtitleService
    ): JsonResponse {
        $this->authorize(
            'update',
            $subtitleJob
        );

        $subtitles =
            $request->validated('subtitles');

        /*
        |--------------------------------------------------------------------------
        | Backend validation
        |--------------------------------------------------------------------------
        | Never rely only on Vue validation.
        |--------------------------------------------------------------------------
        */

        $normalized = [];

        foreach (
            $subtitles as $index => $subtitle
        ) {
            $start =
                (float) $subtitle['start'];

            $end =
                (float) $subtitle['end'];

            $text =
                trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $subtitle['text']
                    )
                );

            if ($end <= $start) {
                return response()->json([
                    'message' =>
                        'Subtitle #' .
                        ($index + 1) .
                        ' has an invalid time range.',
                ], 422);
            }

            if ($text === '') {
                return response()->json([
                    'message' =>
                        'Subtitle #' .
                        ($index + 1) .
                        ' cannot be empty.',
                ], 422);
            }

            $normalized[] = [
                'start' =>
                    round($start, 3),

                'end' =>
                    round($end, 3),

                'text' =>
                    $text,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Sort by start time
        |--------------------------------------------------------------------------
        */

        usort(
            $normalized,
            function ($a, $b) {
                return $a['start'] <=> $b['start'];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Prevent overlapping subtitles
        |--------------------------------------------------------------------------
        */

        for (
            $i = 1;
            $i < count($normalized);
            $i++
        ) {
            $previous =
                $normalized[$i - 1];

            $current =
                $normalized[$i];

            if (
                $current['start']
                < $previous['end']
            ) {
                return response()->json([
                    'message' =>
                        'Subtitle timings cannot overlap. ' .
                        'Please check subtitle #' .
                        ($i + 1) .
                        '.',
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Save using a transaction
        |--------------------------------------------------------------------------
        */

        \DB::transaction(function () use (
            $subtitleJob,
            $normalized,
            $subtitleService
        ) {
            /*
            |--------------------------------------------------------------------------
            | Remove old segments
            |--------------------------------------------------------------------------
            */

            $subtitleJob
                ->segments()
                ->delete();

            /*
            |--------------------------------------------------------------------------
            | Create new segments
            |--------------------------------------------------------------------------
            */

            foreach (
                $normalized as $index => $subtitle
            ) {
                $subtitleJob
                    ->segments()
                    ->create([
                        'sequence' =>
                            $index + 1,

                        'start_time' =>
                            $subtitle['start'],

                        'end_time' =>
                            $subtitle['end'],

                        'text' =>
                            $subtitle['text'],
                    ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Generate SRT
            |--------------------------------------------------------------------------
            */

            $srt =
                $subtitleService->generateSrt(
                    $normalized
                );

            $srtPath =
                'subtitles/' .
                $subtitleJob->id .
                '.srt';

            Storage::disk('public')
                ->put(
                    $srtPath,
                    $srt
                );

            /*
            |--------------------------------------------------------------------------
            | Update job
            |--------------------------------------------------------------------------
            */

            $subtitleJob->update([
                'status' =>
                    'completed',

                'srt_path' =>
                    $srtPath,

                'error_message' =>
                    null,

                'completed_at' =>
                    now(),
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Return fresh subtitles
        |--------------------------------------------------------------------------
        */

        $subtitleJob->refresh();

        $savedSubtitles =
            $subtitleJob
                ->segments()
                ->orderBy('sequence')
                ->get()
                ->map(function ($segment) {
                    return [
                        'id' =>
                            $segment->id,

                        'sequence' =>
                            $segment->sequence,

                        'start_time' =>
                            (float)
                            $segment->start_time,

                        'end_time' =>
                            (float)
                            $segment->end_time,

                        'text' =>
                            $segment->text,
                    ];
                });

        return response()->json([
            'message' =>
                'Subtitles saved and SRT regenerated.',

            'job_id' =>
                $subtitleJob->id,

            'srt_path' =>
                $subtitleJob->srt_path,

            'subtitles' =>
                $savedSubtitles,
        ]);
    }

    /**
     * Download generated SRT.
     */
    public function download(
        SubtitleJob $subtitleJob
    ): BinaryFileResponse|Response {
        $this->authorize(
            'download',
            $subtitleJob
        );

        if (
            !$subtitleJob->srt_path ||
            !Storage::disk('public')
                ->exists(
                    $subtitleJob->srt_path
                )
        ) {
            return response([
                'message' =>
                    'SRT file is not available.',
            ], 404);
        }

        $filename =
            pathinfo(
                $subtitleJob->original_filename,
                PATHINFO_FILENAME
            );

        $filename =
            preg_replace(
                '/[^a-zA-Z0-9\-_]+/',
                '-',
                $filename
            );

        $filename =
            trim(
                $filename,
                '-'
            );

        if ($filename === '') {
            $filename = 'subtitles';
        }

        return response()->download(
            Storage::disk('public')
                ->path(
                    $subtitleJob->srt_path
                ),
            $filename . '.srt',
            [
                'Content-Type' =>
                    'application/x-subrip; charset=UTF-8',
            ]
        );
    }

    /**
     * Stream the uploaded video/audio.
     *
     * This endpoint is protected by the ownership policy.
     */
    public function media(
        SubtitleJob $subtitleJob
    ) {
        $this->authorize(
            'view',
            $subtitleJob
        );

        $disk =
            Storage::disk('public');

        if (
            !$disk->exists(
                $subtitleJob->file_path
            )
        ) {
            return response()->json([
                'message' =>
                    'Media file not found.',
            ], 404);
        }

        return $disk->response(
            $subtitleJob->file_path
        );
    }
    private function splitPreviewLines(
    string $text
): array {

    $words = preg_split(
        '/\s+/u',
        trim($text)
    );

    if (!$words) {
        return [];
    }

    $lines = [];
    $current = '';

    foreach ($words as $word) {

        $candidate = $current === ''
            ? $word
            : $current . ' ' . $word;

        if (
            mb_strlen($candidate) <= 42
        ) {
            $current = $candidate;
            continue;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        $current = $word;
    }

    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}
    /**
     * Delete a subtitle project.
     */
    public function destroy(
        SubtitleJob $subtitleJob
    ): JsonResponse {
        $this->authorize(
            'delete',
            $subtitleJob
        );

        /*
        |--------------------------------------------------------------------------
        | Delete uploaded media
        |--------------------------------------------------------------------------
        */

        if (
            $subtitleJob->file_path &&
            Storage::disk('public')
                ->exists(
                    $subtitleJob->file_path
                )
        ) {
            Storage::disk('public')
                ->delete(
                    $subtitleJob->file_path
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete SRT
        |--------------------------------------------------------------------------
        */

        if (
            $subtitleJob->srt_path &&
            Storage::disk('public')
                ->exists(
                    $subtitleJob->srt_path
                )
        ) {
            Storage::disk('public')
                ->delete(
                    $subtitleJob->srt_path
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete database record
        |--------------------------------------------------------------------------
        |
        | subtitle_segments will be deleted automatically because
        | the foreign key uses cascadeOnDelete().
        |--------------------------------------------------------------------------
        */

        $subtitleJob->delete();

        return response()->json([
            'message' =>
                'Subtitle project deleted successfully.',
        ]);
    }
    public function aiAssist(
    AIAssistRequest $request,
    SubtitleJob $subtitleJob,
    AIService $aiService
): JsonResponse {
    $this->authorize(
        'update',
        $subtitleJob
    );

    try {
        $result = $aiService->assist(
            text: $request->validated('text'),
            action: $request->validated('action'),
            targetLanguage: $request->validated(
                'target_language'
            )
        );

        return response()->json([
            'message' => 'AI assistant completed successfully.',
            'result' => [
                'text' => $result['text'] ?? '',
                'action' => $request->validated('action'),
            ],
        ]);
    } catch (\Throwable $exception) {
        report($exception);

        return response()->json([
            'message' =>
                'AI assistant is currently unavailable.',
        ], 503);
    }
}
}
