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
        Schema::create('internship_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_placement_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->dateTime('checked_in_at');
            $table->dateTime('checked_out_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7);
            $table->decimal('check_in_longitude', 10, 7);
            $table->decimal('check_in_accuracy', 10, 2);
            $table->decimal('check_in_distance', 10, 2);
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->decimal('check_out_accuracy', 10, 2)->nullable();
            $table->decimal('check_out_distance', 10, 2)->nullable();
            $table->unsignedInteger('radius_meters');
            $table->unique(['internship_placement_id', 'work_date'], 'attendance_placement_date_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internship_attendances');
    }
};
