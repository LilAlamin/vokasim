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
        Schema::create('interview_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_position');
            $table->string('difficulty', 30);
            $table->string('interview_type', 30);
            $table->string('interaction_mode', 20);
            $table->string('status', 30)->default('preparing');
            $table->unsignedTinyInteger('question_count');
            $table->unsignedSmallInteger('answer_seconds')->default(180);
            $table->boolean('use_context')->default(false);
            $table->text('context_snapshot');
            $table->timestamp('consented_at');
            $table->string('ai_model');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('star_score', 5, 2)->nullable();
            $table->decimal('technical_score', 5, 2)->nullable();
            $table->decimal('communication_score', 5, 2)->nullable();
            $table->text('evaluation')->nullable();
            $table->string('failure_stage', 20)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_sessions');
    }
};
