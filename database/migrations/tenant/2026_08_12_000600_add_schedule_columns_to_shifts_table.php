<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Upgrade tabel shifts (yang sudah ada) ke pola HRIS:
     * - tambah uuid, work_pattern_id, code, external_code
     * - ganti start_time/end_time → work_hour_start/work_hour_end (kolom lama dibiarkan, tidak dipakai)
     * - tambah window check-in/out, is_overnight, min_work_hours, has_overtime
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->foreignId('work_pattern_id')->nullable()->after('uuid')
                ->constrained('work_patterns')->nullOnDelete();
            $table->string('code')->nullable()->after('name');
            $table->string('external_code')->nullable()->after('code');
            $table->time('work_hour_start')->nullable()->after('external_code');
            $table->time('work_hour_end')->nullable()->after('work_hour_start');
            $table->string('shift_checkin_options')->nullable();
            $table->time('check_in_start')->nullable();
            $table->time('check_in_end')->nullable();
            $table->time('check_out_start')->nullable();
            $table->time('check_out_end')->nullable();
            $table->boolean('is_overnight')->default(false);
            $table->time('check_out_overnight_start')->nullable();
            $table->time('check_out_overnight_end')->nullable();
            $table->integer('min_work_hours')->nullable();
            $table->boolean('has_overtime')->default(false);
            $table->timestamp('synced_at')->nullable();
        });

        // Backfill data lama → kolom baru (kalau ada shift yang sudah terlanjur dibuat)
        $rows = DB::table('shifts')->get();
        foreach ($rows as $row) {
            DB::table('shifts')->where('id', $row->id)->update([
                'uuid' => Str::uuid()->toString(),
                'code' => $row->code ?? strtoupper(Str::slug($row->name, '_')),
                'work_hour_start' => $row->work_hour_start ?? $row->start_time,
                'work_hour_end' => $row->work_hour_end ?? $row->end_time,
            ]);
        }

        // Kolom lama sudah dipindah ke work_hour_start/end → drop biar tidak menghalangi insert baru.
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_pattern_id');
            $table->dropColumn([
                'uuid', 'code', 'external_code', 'work_hour_start', 'work_hour_end',
                'shift_checkin_options', 'check_in_start', 'check_in_end',
                'check_out_start', 'check_out_end', 'is_overnight',
                'check_out_overnight_start', 'check_out_overnight_end',
                'min_work_hours', 'has_overtime', 'synced_at',
            ]);
        });
    }
};
