<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kalender kerja per tahun — sumber libur & hari kerja efektif.
     * Pola kolom nyamain sch_working_calendars (HRIS) biar siap sync/pull.
     */
    public function up(): void
    {
        Schema::create('working_calendars', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->integer('year');
            $table->text('description')->nullable();
            $table->string('external_code')->nullable(); // kode mapping integrasi HRIS
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_calendars');
    }
};
