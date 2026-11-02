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
        Schema::create('internship_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_placement_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason');
            $table->string('evidence_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('feedback')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['internship_placement_id', 'status', 'starts_on'], 'leave_placement_status_date_index');
        });

        Schema::create('industry_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_placement_id')->constrained()->restrictOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->restrictOnDelete();
            $table->string('period_type', 20);
            $table->date('period_month');
            $table->unsignedTinyInteger('work_quality');
            $table->unsignedTinyInteger('tool_mastery');
            $table->unsignedTinyInteger('discipline');
            $table->unsignedTinyInteger('teamwork');
            $table->unsignedTinyInteger('professional_ethics');
            $table->decimal('final_score', 5, 2);
            $table->text('feedback');
            $table->text('improvement_plan');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['internship_placement_id', 'period_type', 'period_month'], 'evaluation_placement_period_unique');
        });

        Schema::create('internship_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_placement_id')->constrained()->restrictOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->date('starts_on');
            $table->date('due_on');
            $table->unsignedInteger('target_minutes');
            $table->unsignedInteger('completed_minutes')->default(0);
            $table->text('feedback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internship_projects');
        Schema::dropIfExists('industry_evaluations');
        Schema::dropIfExists('internship_leave_requests');
    }
};
