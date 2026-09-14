<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubtitleJob extends Model
{
    protected $fillable = [
        'user_id',
        'original_filename',
        'file_path',
        'status',
        'language',
        'model',
        'transcript',
        'srt_path',
        'error_message',
        'file_size',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'transcript' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function segments(): HasMany
    {
        return $this->hasMany(SubtitleSegment::class)
            ->orderBy('sequence');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
