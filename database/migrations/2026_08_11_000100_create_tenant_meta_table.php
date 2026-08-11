<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapping tenant di DB Absensi pusat (bukan central_db milik Central Platform).
     * Dibuat saat provisioning webhook dari Central diterima.
     */
    public function up(): void
    {
        Schema::create('tenant_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_tenant_id')->index();
            $table->string('slug')->unique();
            $table->string('db_name');
            $table->string('status')->default('provisioning'); // provisioning / active / suspended
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_meta');
    }
};
