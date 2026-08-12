<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rekening bank karyawan — satu karyawan bisa punya beberapa rekening,
     * is_default = rekening utama (mis. buat payroll).
     * Pola kolom (uuid/external_code/synced_at) disamain dengan HRIS biar siap sync/pull.
     */
    public function up(): void
    {
        Schema::create('employee_banks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('employee_id');
            $table->string('bank_name', 100);
            $table->string('account_number', 100);
            $table->string('account_name', 200);
            $table->string('branch', 100)->nullable();
            $table->boolean('is_default')->default(false);
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
        Schema::dropIfExists('employee_banks');
    }
};
