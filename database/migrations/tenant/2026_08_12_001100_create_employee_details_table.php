<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detail personal karyawan (1:1 dengan employees).
     * Pola kolom (uuid/external_code/synced_at) disamain dengan HRIS biar siap sync/pull.
     */
    public function up(): void
    {
        Schema::create('employee_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('employee_id')->unique(); // 1:1
            $table->string('nik', 50)->nullable()->unique();
            $table->string('gender', 1)->nullable(); // L / P
            $table->string('religion', 50)->nullable();
            $table->string('blood_type', 10)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('place_of_birth', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('npwp', 50)->nullable();
            $table->string('external_code')->nullable(); // kode mapping integrasi HRIS
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_details');
    }
};
