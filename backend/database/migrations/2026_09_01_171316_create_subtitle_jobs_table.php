<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtitle_jobs', function (Blueprint $table) {
            $table->id();

            $table->string('original_filename');
            $table->string('file_path');

            $table->string('status')->default('pending');

            $table->string('language')->nullable();

            $table->string('model')->default('medium');

            $table->json('transcript')->nullable();

            $table->string('srt_path')->nullable();

            $table->text('error_message')->nullable();

            $table->unsignedBigInteger('file_size')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtitle_jobs');
    }
};
