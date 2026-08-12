<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keluarga & tanggungan karyawan.
     * relation string (Suami/Istri/Anak/Orang Tua) — bukan tabel lookup biar ringan.
     * Pola kolom (uuid/external_code/synced_at) disamain dengan HRIS biar siap sync/pull.
     */
    public function up(): void
    {
        Schema::create('employee_families', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('employee_id');
            $table->string('relation', 50); // Suami / Istri / Anak / Orang Tua / dll
            $table->string('name', 200);
            $table->string('gender', 1)->nullable(); // L / P
            $table->string('nik', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('education_level', 50)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->boolean('is_dependent')->default(false); // tanggungan pajak/BPJS
            $table->boolean('is_emergency_contact')->default(false);
            $table->string('emergency_phone', 50)->nullable();
            $table->string('external_code')->nullable(); // kode mapping integrasi HRIS
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_families');
    }
};
