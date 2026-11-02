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
        Schema::create('tracer_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('graduation_year');
            $table->date('graduated_on');
            $table->string('study_program')->nullable();
            $table->string('employment_status', 30);
            $table->string('employer_name')->nullable();
            $table->string('job_title')->nullable();
            $table->foreignId('partner_company_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('first_job_started_on')->nullable();
            $table->unsignedInteger('wait_months')->nullable();
            $table->unsignedBigInteger('first_monthly_salary')->nullable();
            $table->string('field_alignment', 20)->nullable();
            $table->json('skill_gaps')->nullable();
            $table->text('curriculum_feedback')->nullable();
            $table->timestamp('consented_at');
            $table->timestamps();
            $table->index(['graduation_year', 'study_program']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracer_responses');
    }
};
