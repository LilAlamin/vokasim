<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interview_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('category', 30);
            $table->text('prompt');
            $table->text('answer_text')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('video_path')->nullable();
            $table->decimal('recorded_seconds', 8, 2)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->boolean('timed_out')->default(false);
            $table->text('evaluation')->nullable();
            $table->timestamps();
            $table->unique(['interview_session_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_questions');
    }
};
