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
        Schema::create('partner_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sector');
            $table->string('address', 500);
            $table->string('city', 100);
            $table->string('province', 100);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->unsignedInteger('internship_quota')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('partner_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('address', 500);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->string('timezone', 40)->default('Asia/Jakarta');
            $table->unsignedInteger('internship_quota')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['partner_company_id', 'name']);
        });

        Schema::create('partner_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_company_id')->constrained()->restrictOnDelete();
            $table->string('reference_number', 150);
            $table->string('kind', 10);
            $table->date('signed_on');
            $table->date('starts_on');
            $table->date('expires_on');
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['partner_company_id', 'reference_number']);
            $table->index(['is_active', 'expires_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_agreements');
        Schema::dropIfExists('partner_units');
        Schema::dropIfExists('partner_companies');
    }
};
