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
        Schema::create('practicum_rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practicum_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 20);
            $table->decimal('max_score', 6, 2)->default(100);
            $table->decimal('weight', 6, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practicum_rubric_criteria');
    }
};
