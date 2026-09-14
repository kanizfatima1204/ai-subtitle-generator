<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtitle_segments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subtitle_job_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('sequence');

            $table->decimal('start_time', 10, 3);
            $table->decimal('end_time', 10, 3);

            $table->text('text');

            $table->timestamps();

            $table->index([
                'subtitle_job_id',
                'sequence',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtitle_segments');
    }
};
