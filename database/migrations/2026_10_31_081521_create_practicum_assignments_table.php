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
        Schema::create('practicum_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practicum_module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions');
            $table->timestamp('due_at');
            $table->decimal('weight', 6, 2)->default(1);
            $table->boolean('allow_late')->default(false);
            $table->timestamps();
            $table->index(['practicum_module_id', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practicum_assignments');
    }
};
