<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Template wajah per karyawan (hasil enroll face-api.js).
     * Disimpan di server (per-tenant DB); mode client/server sesuai pengaturan tenant.
     */
    public function up(): void
    {
        Schema::create('face_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->longText('template'); // embedding/descriptor (JSON)
            $table->string('mode')->default('server'); // client / server
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_templates');
    }
};
