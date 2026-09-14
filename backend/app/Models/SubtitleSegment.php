<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubtitleSegment extends Model
{
    protected $fillable = [
        'subtitle_job_id',
        'sequence',
        'start_time',
        'end_time',
        'text',
    ];

    protected $casts = [
        'start_time' => 'decimal:3',
        'end_time' => 'decimal:3',
    ];

    public function subtitleJob(): BelongsTo
    {
        return $this->belongsTo(SubtitleJob::class);
    }
}
