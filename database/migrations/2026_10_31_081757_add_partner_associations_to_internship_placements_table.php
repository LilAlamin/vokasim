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
        Schema::table('internship_placements', function (Blueprint $table) {
            $table->foreignId('partner_company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('partner_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['partner_company_id', 'starts_on', 'ends_on'], 'placement_partner_period_index');
            $table->index(['partner_unit_id', 'starts_on', 'ends_on'], 'placement_unit_period_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internship_placements', function (Blueprint $table) {
            $table->dropIndex('placement_partner_period_index');
            $table->dropIndex('placement_unit_period_index');
            $table->dropConstrainedForeignId('partner_unit_id');
            $table->dropConstrainedForeignId('partner_company_id');
        });
    }
};
