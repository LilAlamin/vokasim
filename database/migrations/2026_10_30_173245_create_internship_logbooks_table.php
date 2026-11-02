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
        Schema::create('internship_logbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_placement_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->text('description');
            $table->unsignedSmallInteger('effective_minutes');
            $table->string('photo_path')->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('mentor_approved_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->unique(['internship_placement_id', 'work_date'], 'logbook_placement_date_unique');
            $table->index(['status', 'work_date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internship_logbooks');
    }
};
