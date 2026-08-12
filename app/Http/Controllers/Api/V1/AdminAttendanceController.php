<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Rekap absensi karyawan untuk admin (superadmin/hrmanager):
 * roster per karyawan × per tanggal + detail harian (jam & foto clock in/out).
 */
class AdminAttendanceController extends Controller
{
    /** Batas aman rentang tanggal per request (bulan = 31, range bebas dibatasi). */
    private const MAX_RANGE_DAYS = 93;

    /**
     * GET /api/v1/attendance/roster?from=Y-m-d&to=Y-m-d[&group_id=N]
     * Baris = karyawan aktif, kolom = tanggal, sel = ringkasan clock in/out.
     */
    public function roster(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group_id' => ['nullable', 'integer', 'exists:employee_groups,id'],
        ]);

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        if ((int) $from->diffInDays($to) + 1 > self::MAX_RANGE_DAYS) {
            abort(422, 'Rentang tanggal maksimal '.self::MAX_RANGE_DAYS.' hari.');
        }

        $employees = Employee::query()
            ->with(['attendances' => fn ($q) => $q
                ->whereBetween('recorded_at', [$from->startOfDay()->toDateTimeString(), $to->endOfDay()->toDateTimeString()])
                ->orderBy('recorded_at')])
            ->where('status', 'active')
            ->when($request->filled('group_id'), fn ($q) => $q->whereHas(
                'groups',
                fn ($g) => $g->where('employee_groups.id', $request->integer('group_id'))
            ))
            ->orderBy('name')
            ->get();

        $dates = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        $rows = $employees->map(function (Employee $employee) use ($dates) {
            $byDate = $employee->attendances->groupBy(fn ($a) => $a->recorded_at->toDateString());

            $days = array_map(function (string $date) use ($byDate) {
                $records = $byDate->get($date) ?? collect();

                $clockIn = $records->where('type', 'clock_in')->sortBy('recorded_at')->first();
                $clockOut = $records->where('type', 'clock_out')->sortByDesc('recorded_at')->first();

                return [
                    'clock_in' => $clockIn?->recorded_at->format('H:i'),
                    'clock_out' => $clockOut?->recorded_at->format('H:i'),
                    'count_in' => $records->where('type', 'clock_in')->count(),
                    'count_out' => $records->where('type', 'clock_out')->count(),
                    'has_selfie' => $records->contains(
                        fn ($a) => $a->selfie_photo !== null && $a->selfie_photo !== ''
                    ),
                ];
            }, $dates);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'position' => $employee->position,
                'days' => $days,
            ];
        });

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'dates' => $dates,
                'employees' => $rows,
            ],
        ]);
    }

    /**
     * GET /api/v1/attendance/roster/{employee}?date=Y-m-d
     * Detail semua record absen satu karyawan di satu tanggal (termasuk foto selfie).
     */
    public function detail(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $records = $employee->attendances()
            ->whereBetween('recorded_at', [$validated['date'].' 00:00:00', $validated['date'].' 23:59:59'])
            ->with('workLocation:id,name')
            ->orderBy('recorded_at')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'time' => $a->recorded_at->format('H:i'),
                'recorded_at' => $a->recorded_at->toISOString(),
                'selfie_photo' => $a->selfie_photo,
                'latitude' => $a->latitude,
                'longitude' => $a->longitude,
                'distance_meter' => $a->distance_meter,
                'status' => $a->status,
                'notes' => $a->notes,
                'work_location' => $a->workLocation?->name,
            ]);

        return response()->json([
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'position' => $employee->position,
                ],
                'date' => $validated['date'],
                'records' => $records,
            ],
        ]);
    }

    /**
     * GET /api/v1/attendance/export?from=Y-m-d&to=Y-m-d[&format=csv]
     * Export rekap absensi ke CSV (per record clock in/out) untuk diolah HR.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group_id' => ['nullable', 'integer', 'exists:employee_groups,id'],
        ]);

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        if ((int) $from->diffInDays($to) + 1 > self::MAX_RANGE_DAYS) {
            abort(422, 'Rentang tanggal maksimal '.self::MAX_RANGE_DAYS.' hari.');
        }

        $records = Attendance::with(['employee:id,name,position', 'workLocation:id,name'])
            ->whereBetween('recorded_at', [$from->startOfDay()->toDateTimeString(), $to->endOfDay()->toDateTimeString()])
            ->when($request->filled('group_id'), fn ($q) => $q->whereHas(
                'employee.groups',
                fn ($g) => $g->where('employee_groups.id', $request->integer('group_id'))
            ))
            ->orderBy('recorded_at')
            ->get();

        $fileName = 'absensi-'.$validated['from'].'_'.$validated['to'].'.csv';

        $handle = tmpfile();
        $csv = new \SplFileObject(stream_get_meta_data($handle)['uri'], 'w');

        $csv->fputcsv([
            'Tanggal', 'Jam', 'Tipe', 'Nama Karyawan', 'Jabatan',
            'Lokasi', 'Latitude', 'Longitude', 'Jarak (m)',
            'Status', 'Catatan', 'Wajah Terverifikasi',
        ]);

        foreach ($records as $a) {
            $csv->fputcsv([
                $a->recorded_at->toDateString(),
                $a->recorded_at->format('H:i'),
                $a->type,
                $a->employee?->name ?? '-',
                $a->employee?->position ?? '-',
                $a->workLocation?->name ?? '-',
                $a->latitude,
                $a->longitude,
                $a->distance_meter,
                $a->status,
                $a->notes,
                $a->face_verified ? 'Ya' : 'Tidak',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $tempPath = tempnam(sys_get_temp_dir(), 'absensi_export_');
        file_put_contents($tempPath, $content);

        return response()
            ->download($tempPath, $fileName, ['Content-Type' => 'text/csv'])
            ->deleteFileAfterSend();
    }

    private function employee(Request $request): Employee
    {
        return $request->user()->employee;
    }
}
