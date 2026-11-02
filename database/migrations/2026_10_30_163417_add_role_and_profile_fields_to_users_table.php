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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('mahasiswa')->after('email');
            $table->string('identifier_number')->nullable()->after('role');
            $table->string('study_program')->nullable()->after('identifier_number');
            $table->string('institution_or_company')->nullable()->after('study_program');
            $table->string('phone')->nullable()->after('institution_or_company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'identifier_number',
                'study_program',
                'institution_or_company',
                'phone',
            ]);
        });
    }
};
