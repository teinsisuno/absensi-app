<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen karyawan (KTP, KK, ijazah, sertifikat, dll).
     * document_type string (bukan tabel lookup) biar ringan & fleksibel.
     * Pola kolom (uuid/external_code/synced_at) disamain dengan HRIS biar siap sync/pull.
     */
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('employee_id');
            $table->string('document_type', 100); // KTP / KK / Ijazah / Sertifikat / dll
            $table->string('document_number', 100)->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('file_path', 255);
            $table->string('file_name', 200)->nullable();
            $table->string('file_size', 50)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issued_by', 200)->nullable();
            $table->string('verification_status', 20)->default('pending'); // pending / verified / rejected
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
        Schema::dropIfExists('employee_documents');
    }
};
