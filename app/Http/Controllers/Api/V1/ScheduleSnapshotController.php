<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ScheduleSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleSnapshotController extends Controller
{
    /**
     * GET /api/v1/schedule-snapshots — daftar jadwal, filter employee_id, group_id, from, to.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ScheduleSnapshot::query()
            ->with(['employee:id,name,position', 'shift:id,name,code,work_hour_start,work_hour_end'])
            ->orderBy('date')
            ->orderBy('employee_id');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('from')) {
            $query->where('date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('date', '<=', $request->date('to'));
        }

        if ($request->filled('group_id')) {
            $query->whereHas('employee.groups', fn ($q) => $q->where('employee_groups.id', $request->integer('group_id')));
        }

        return response()->json(['data' => $query->limit(1000)->get()]);
    }

    /**
     * GET /api/v1/schedule-snapshots/me — jadwal karyawan sendiri (buat calendar PWA).
     * Filter: from, to (default bulan berjalan kalau kosong).
     * Supervisor: kirim ?group_id= → jadwal SEMUA member group yang dia pimpin.
     */
    public function mySchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group_id' => ['nullable', 'integer', 'exists:employee_groups,id'],
        ]);

        $employee = $request->user()->employee;

        $from = $validated['from'] ?? now()->startOfMonth()->format('Y-m-d');
        $to = $validated['to'] ?? now()->endOfMonth()->format('Y-m-d');

        $query = ScheduleSnapshot::query()
            ->with(['shift:id,name,code,work_hour_start,work_hour_end', 'employee:id,name,position']);

        // Supervisor lihat jadwal group yang dia pimpin
        if (! empty($validated['group_id'])) {
            $group = \App\Models\EmployeeGroup::find($validated['group_id']);

            if (! $group || (int) $group->supervisor_id !== (int) $employee->id) {
                return response()->json(['message' => 'Kamu bukan supervisor group ini.'], 403);
            }

            $query->whereIn(
                'employee_id',
                $group->members()->pluck('employees.id')
            );
        } else {
            // Karyawan biasa → jadwal sendiri
            $query->where('employee_id', $employee->id);
        }

        $snapshots = $query
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        return response()->json(['data' => $snapshots]);
    }

    /**
     * POST /api/v1/schedule-snapshots — set jadwal (single atau bulk).
     * Body: { employee_ids: [], date | from+to, shift_id?, work_pattern_id?, source? }
     * Kalau snapshot sudah ada di tanggal tsb → di-update (upsert).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'date' => ['required_without:from', 'date'],
            'from' => ['required_without:date', 'date'],
            'to' => ['required_with:from', 'date', 'after_or_equal:from'],
            'shift_id' => ['sometimes', 'nullable', 'exists:shifts,id'],
            'work_pattern_id' => ['sometimes', 'nullable', 'exists:work_patterns,id'],
            'status' => ['sometimes', 'in:scheduled,confirmed,cancelled'],
            'source' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $dates = isset($validated['date'])
            ? [$validated['date']]
            : collect(\Illuminate\Support\Carbon::parse($validated['from'])->toPeriod($validated['to']))
                ->map(fn ($d) => $d->format('Y-m-d'))
                ->all();

        $shift = $validated['shift_id'] ?? null;
        $pattern = $validated['work_pattern_id'] ?? null;
        $status = $validated['status'] ?? 'scheduled';
        $source = $validated['source'] ?? 'manual';
        $notes = $validated['notes'] ?? null;

        $shiftInfo = $shift ? \App\Models\Shift::find($shift) : null;

        $created = 0;
        foreach ($validated['employee_ids'] as $employeeId) {
            foreach ($dates as $date) {
                ScheduleSnapshot::updateOrCreate(
                    ['employee_id' => $employeeId, 'date' => $date],
                    [
                        'shift_id' => $shift,
                        'work_pattern_id' => $pattern,
                        'shift_code' => $shiftInfo?->code,
                        'status' => $status,
                        'source' => $source,
                        'notes' => $notes,
                    ]
                );
                $created++;
            }
        }

        return response()->json(['message' => "Jadwal disimpan untuk {$created} entri."], 201);
    }

    /**
     * PUT /api/v1/schedule-snapshots/{id} — edit satu entri jadwal.
     */
    public function update(Request $request, ScheduleSnapshot $scheduleSnapshot): JsonResponse
    {
        $validated = $request->validate([
            'shift_id' => ['sometimes', 'nullable', 'exists:shifts,id'],
            'work_pattern_id' => ['sometimes', 'nullable', 'exists:work_patterns,id'],
            'status' => ['sometimes', 'in:scheduled,confirmed,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if (isset($validated['shift_id'])) {
            $shift = $validated['shift_id'] ? \App\Models\Shift::find($validated['shift_id']) : null;
            $validated['shift_code'] = $shift?->code;
        }

        $scheduleSnapshot->update($validated);

        return response()->json(['data' => $scheduleSnapshot->fresh()]);
    }

    /**
     * DELETE /api/v1/schedule-snapshots/{id} — hapus satu entri jadwal.
     */
    public function destroy(ScheduleSnapshot $scheduleSnapshot): JsonResponse
    {
        $scheduleSnapshot->delete();

        return response()->json(['message' => 'Jadwal dihapus.']);
    }
}
