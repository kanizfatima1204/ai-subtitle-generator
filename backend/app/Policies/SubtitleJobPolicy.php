<?php

namespace App\Policies;

use App\Models\SubtitleJob;
use App\Models\User;

class SubtitleJobPolicy
{
    /**
     * Determine whether the user can view the job.
     */
    public function view(
        User $user,
        SubtitleJob $subtitleJob
    ): bool {
        return $user->id === $subtitleJob->user_id;
    }

    /**
     * Determine whether the user can update the job.
     */
    public function update(
        User $user,
        SubtitleJob $subtitleJob
    ): bool {
        return $user->id === $subtitleJob->user_id;
    }

    /**
     * Determine whether the user can download the job.
     */
    public function download(
        User $user,
        SubtitleJob $subtitleJob
    ): bool {
        return $user->id === $subtitleJob->user_id;
    }

    /**
     * Determine whether the user can delete the job.
     */
    public function delete(
        User $user,
        SubtitleJob $subtitleJob
    ): bool {
        return $user->id === $subtitleJob->user_id;
    }
}
