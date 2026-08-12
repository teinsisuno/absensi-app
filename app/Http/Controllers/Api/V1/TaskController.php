<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tugas — HR/management memberi tugas & memantau; karyawan melihat & update status.
 */
class TaskController extends Controller
{
    /**
     * GET /api/v1/tasks — admin: semua tugas (filter ?assignee_id=&status=).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', 'in:pending,in_progress,done'],
        ]);

        $tasks = Task::with(['assignee:id,name,position', 'creator:id,name'])
            ->when($validated['assignee_id'] ?? null, fn ($q, $id) => $q->where('assignee_id', $id))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $tasks]);
    }

    /**
     * GET /api/v1/tasks/me — karyawan: tugas miliknya (filter ?status=).
     */
    public function myTasks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,in_progress,done'],
        ]);

        $tasks = $this->employee($request)->assignedTasks()
            ->with('creator:id,name')
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $tasks]);
    }

    /**
     * POST /api/v1/tasks — admin: buat tugas baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignee_id' => ['required', 'integer', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date', 'date_format:Y-m-d'],
        ]);

        $task = Task::create([
            'created_by' => $request->user()->id,
            'assignee_id' => $validated['assignee_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Tugas diberikan.',
            'data' => $task->fresh(['assignee:id,name,position']),
        ], 201);
    }

    /**
     * PUT /api/v1/tasks/{task} — admin: edit tugas.
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'assignee_id' => ['required', 'integer', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'done'])],
        ]);

        $task->update($validated);

        return response()->json(['data' => $task->fresh(['assignee:id,name,position'])]);
    }

    /**
     * PUT /api/v1/tasks/{task}/status — karyawan: update status tugas miliknya.
     */
    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $employee = $this->employee($request);

        if ((int) $task->assignee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Tugas ini bukan untuk kamu.'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'done'])],
        ]);

        $task->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Status tugas diperbarui.',
            'data' => $task->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/tasks/{task} — admin: hapus tugas.
     */
    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Tugas dihapus.']);
    }

    private function employee(Request $request): Employee
    {
        return $request->user()->employee;
    }
}
