<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pola kerja — durasi kerja, istirahat, aturan lembur (satuan JAM).
     * Kolom nyamain sch_work_patterns (HRIS): work_day_hours SUDAH termasuk istirahat.
     */
    public function up(): void
    {
        Schema::create('work_patterns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('employee_type')->nullable();

            $table->integer('cut_off_date')->nullable();
            $table->boolean('sun_overtime')->default(false);

            $table->integer('work_day')->default(5);
            $table->string('sat_type')->default('off'); // off / full / half
            $table->boolean('is_half_day_all')->default(false);

            $table->integer('work_day_hours')->default(8); // jam kerja (termasuk istirahat)
            $table->integer('half_day_hours')->default(4);
            $table->integer('wd_rest_hours')->default(1);  // istirahat hari kerja
            $table->integer('hd_rest_hours')->default(0);  // istirahat hari setengah

            $table->string('external_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_patterns');
    }
};
