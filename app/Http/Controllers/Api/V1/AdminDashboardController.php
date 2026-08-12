<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\Holiday;
use App\Models\ScheduleSnapshot;
use App\Models\Shift;
use App\Models\WorkPattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ringkasan angka untuk dashboard admin.
 */
class AdminDashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $year = $request->integer('year', (int) now()->year);
        $today = now()->toDateString();

        return response()->json([
            'data' => [
                'employees_active' => Employee::where('status', 'active')->count(),
                'employees_total' => Employee::count(),
                'groups' => EmployeeGroup::count(),
                'shifts_active' => Shift::where('is_active', true)->count(),
                'work_patterns' => WorkPattern::where('is_active', true)->count(),
                'holidays_year' => Holiday::whereYear('date', $year)->count(),
                'snapshots_month' => ScheduleSnapshot::whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count(),
                'clock_in_today' => Attendance::where('type', 'clock_in')->whereDate('recorded_at', $today)->count(),
                'clock_out_today' => Attendance::where('type', 'clock_out')->whereDate('recorded_at', $today)->count(),
            ],
        ]);
    }
}
