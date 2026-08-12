<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot jadwal per karyawan per tanggal — sumber validasi absen harian.
     * Nyamain sch_employee_shift_rosters (HRIS): shift_code, is_holiday/leave/permit, status, source, metadata.
     */
    public function up(): void
    {
        Schema::create('schedule_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('work_pattern_id')->nullable()->constrained('work_patterns')->nullOnDelete();
            $table->date('date');
            $table->string('shift_code')->nullable();
            $table->string('work_pattern_type')->nullable();
            $table->string('external_code')->nullable();

            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_sat')->default(false);
            $table->boolean('is_sun')->default(false);
            $table->boolean('is_half_day')->default(false);

            $table->boolean('is_leave')->default(false);
            $table->boolean('is_permit')->default(false);
            $table->unsignedBigInteger('leave_id')->nullable();

            $table->string('status')->default('scheduled'); // scheduled / confirmed / cancelled
            $table->string('source')->nullable(); // manual / work_pattern / holiday / hris_pull
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('date');
            $table->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_snapshots');
    }
};
