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
        Schema::create('practicum_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecturer_id')->constrained('users')->restrictOnDelete();
            $table->string('code', 80)->unique();
            $table->string('title');
            $table->string('study_program');
            $table->unsignedTinyInteger('semester');
            $table->string('skill_area');
            $table->text('description');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->index(['lecturer_id', 'published']);
            $table->index(['study_program', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practicum_modules');
    }
};
