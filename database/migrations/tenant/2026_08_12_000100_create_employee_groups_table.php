<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group karyawan — relasi many-to-many ke employees (satu karyawan bisa di beberapa group).
     * supervisor_id = kepala group (optional; dipakai scope supervisor di PWA).
     * Pola kolom (uuid/external_code/synced_at) disamain dengan HRIS biar siap sync/pull.
     */
    public function up(): void
    {
        Schema::create('employee_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable(); // kepala group (employees)
            $table->string('external_code')->nullable(); // kode mapping integrasi HRIS
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supervisor_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_groups');
    }
};
