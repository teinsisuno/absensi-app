<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kontrak kerja karyawan — riwayat kontrak, versi terbaru ditandai is_latest.
     * contract_type string (PKWT/PKWTT/dll) — bukan tabel lookup biar ringan.
     * Pola kolom (uuid/external_code/synced_at) disamain dengan HRIS biar siap sync/pull.
     */
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('employee_id');
            $table->string('contract_number', 100)->nullable()->unique();
            $table->string('contract_type', 50)->default('pkwt'); // pkwt / pkwtt / magang / dll
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('duration_months')->nullable();
            $table->string('document_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_latest')->default(true);
            $table->string('status', 20)->default('active'); // active / expired / terminated / draft
            $table->timestamp('expiry_notified_at')->nullable();
            $table->string('external_code')->nullable(); // kode mapping integrasi HRIS
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->index(['employee_id', 'status', 'is_latest']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
