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
        Schema::create('practicum_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practicum_module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('room');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_exam')->default(false);
            $table->boolean('cancelled')->default(false);
            $table->timestamps();
            $table->index(['practicum_module_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practicum_sessions');
    }
};
