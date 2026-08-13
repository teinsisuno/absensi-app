<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeBank;
use App\Models\EmployeeContract;
use App\Models\EmployeeDetail;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFamily;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Submodule data karyawan (admin): detail personal 1:1, bank, keluarga,
 * kontrak kerja, dan dokumen. Semua endpoint bersarang di /employees/{employee}/…
 * dengan pengecekan kepemilikan (sub-resource harus milik employee tsb).
 *
 * Pola kolom uuid/external_code/synced_at disamakan dengan HRIS instance1
 * biar siap integrasi sync/pull.
 */
class EmployeeSubmoduleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Detail personal (1:1)
    |--------------------------------------------------------------------------
    */

    /**
     * PUT /api/v1/employees/{employee}/detail — buat/ubah detail personal (upsert 1:1).
     */
    public function updateDetail(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'nik' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:L,P'],
            'religion' => ['nullable', 'string', 'max:50'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'marital_status' => ['nullable', 'string', 'max:20'],
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'date_format:Y-m-d'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'npwp' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $detail = EmployeeDetail::updateOrCreate(
                ['employee_id' => $employee->id],
                $validated
            );

            return response()->json([
                'message' => 'Detail personal diperbarui.',
                'data' => $detail->fresh(),
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Rekening bank
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/v1/employees/{employee}/banks — daftar rekening karyawan.
     */
    public function indexBanks(Employee $employee): JsonResponse
    {
        return response()->json([
            'data' => $employee->banks()->orderByDesc('is_default')->orderBy('bank_name')->get(),
        ]);
    }

    /**
     * POST /api/v1/employees/{employee}/banks — tambah rekening.
     */
    public function storeBank(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:200'],
            'branch' => ['nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        try {
            $bank = DB::transaction(function () use ($employee, $validated) {
                if (!empty($validated['is_default'])) {
                    $employee->banks()->update(['is_default' => false]);
                }
                return $employee->banks()->create($validated);
            });

            return response()->json(['data' => $bank->fresh()], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/v1/employees/{employee}/banks/{bank} — edit rekening.
     */
    public function updateBank(Request $request, Employee $employee, EmployeeBank $bank): JsonResponse
    {
        abort_unless($bank->employee_id === $employee->id, 404, 'Rekening tidak ditemukan.');

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:200'],
            'branch' => ['nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($employee, $bank, $validated) {
            if (!empty($validated['is_default'])) {
                $employee->banks()->where('id', '!=', $bank->id)->update(['is_default' => false]);
            }
            $bank->update($validated);
        });

        return response()->json(['data' => $bank->fresh()]);
    }

    /**
     * DELETE /api/v1/employees/{employee}/banks/{bank} — hapus rekening.
     */
    public function destroyBank(Employee $employee, EmployeeBank $bank): JsonResponse
    {
        abort_unless($bank->employee_id === $employee->id, 404, 'Rekening tidak ditemukan.');

        $bank->delete();

        return response()->json(['message' => 'Rekening dihapus.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Keluarga
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/v1/employees/{employee}/families — daftar keluarga/tanggungan.
     */
    public function indexFamilies(Employee $employee): JsonResponse
    {
        return response()->json([
            'data' => $employee->families()->orderByDesc('created_at')->get(),
        ]);
    }

    /**
     * POST /api/v1/employees/{employee}/families — tambah anggota keluarga.
     */
    public function storeFamily(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate($this->familyRules());

        try {
            $family = $employee->families()->create($validated);

            return response()->json(['data' => $family->fresh()], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/v1/employees/{employee}/families/{family} — edit anggota keluarga.
     */
    public function updateFamily(Request $request, Employee $employee, EmployeeFamily $family): JsonResponse
    {
        abort_unless($family->employee_id === $employee->id, 404, 'Data keluarga tidak ditemukan.');

        $validated = $request->validate($this->familyRules());
        $family->update($validated);

        return response()->json(['data' => $family->fresh()]);
    }

    /**
     * DELETE /api/v1/employees/{employee}/families/{family} — hapus anggota keluarga.
     */
    public function destroyFamily(Employee $employee, EmployeeFamily $family): JsonResponse
    {
        abort_unless($family->employee_id === $employee->id, 404, 'Data keluarga tidak ditemukan.');

        $family->delete();

        return response()->json(['message' => 'Data keluarga dihapus.']);
    }

    private function familyRules(): array
    {
        return [
            'relation' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'gender' => ['nullable', 'in:L,P'],
            'nik' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'date_format:Y-m-d'],
            'education_level' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'is_dependent' => ['sometimes', 'boolean'],
            'is_emergency_contact' => ['sometimes', 'boolean'],
            'emergency_phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Kontrak kerja
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/v1/employees/{employee}/contracts — riwayat kontrak (terbaru dulu).
     */
    public function indexContracts(Employee $employee): JsonResponse
    {
        return response()->json([
            'data' => $employee->contracts()->orderByDesc('start_date')->get(),
        ]);
    }

    /**
     * POST /api/v1/employees/{employee}/contracts — tambah kontrak baru.
     * Kontrak baru otomatis jadi is_latest; kontrak lama diturunkan.
     */
    public function storeContract(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate($this->contractRules());

        try {
            $contract = DB::transaction(function () use ($employee, $validated) {
                $employee->contracts()->update(['is_latest' => false]);

                return $employee->contracts()->create([
                    ...$validated,
                    'contract_type' => $validated['contract_type'] ?? 'pkwt',
                    'duration_months' => $this->resolveDurationMonths($validated),
                    'is_latest' => true,
                    'status' => $validated['status'] ?? 'active',
                ]);
            });

            return response()->json(['data' => $contract->fresh()], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/v1/employees/{employee}/contracts/{contract} — edit kontrak.
     */
    public function updateContract(Request $request, Employee $employee, EmployeeContract $contract): JsonResponse
    {
        abort_unless($contract->employee_id === $employee->id, 404, 'Kontrak tidak ditemukan.');

        $validated = $request->validate($this->contractRules());

        $contract->update([
            ...$validated,
            'duration_months' => $this->resolveDurationMonths($validated) ?? $contract->duration_months,
        ]);

        return response()->json(['data' => $contract->fresh()]);
    }

    /**
     * DELETE /api/v1/employees/{employee}/contracts/{contract} — hapus kontrak.
     */
    public function destroyContract(Employee $employee, EmployeeContract $contract): JsonResponse
    {
        abort_unless($contract->employee_id === $employee->id, 404, 'Kontrak tidak ditemukan.');

        $contract->delete();

        return response()->json(['message' => 'Kontrak dihapus.']);
    }

    private function contractRules(): array
    {
        return [
            'contract_number' => ['nullable', 'string', 'max:100'],
            'contract_type' => ['sometimes', 'string', 'max:50'],
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'document_path' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::in(['active', 'expired', 'terminated', 'draft'])],
        ];
    }

    private function resolveDurationMonths(array $validated): ?int
    {
        if (isset($validated['duration_months'])) {
            return $validated['duration_months'];
        }

        $start = $validated['start_date'] ?? null;
        $end = $validated['end_date'] ?? null;

        if ($start && $end) {
            return max(1, (int) \Carbon\Carbon::parse($start)->diffInMonths(\Carbon\Carbon::parse($end)) + 1);
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Dokumen
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/v1/employees/{employee}/documents — daftar dokumen karyawan.
     */
    public function indexDocuments(Employee $employee): JsonResponse
    {
        return response()->json([
            'data' => $employee->documents()->orderByDesc('created_at')->get(),
        ]);
    }

    /**
     * POST /api/v1/employees/{employee}/documents — tambah dokumen (metadata).
     * Upload file fisik bisa menyusul — untuk sekarang file_path diisi URL/path string.
     */
    public function storeDocument(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate($this->documentRules());

        try {
            $document = $employee->documents()->create([
                ...$validated,
                'verification_status' => $validated['verification_status'] ?? 'pending',
            ]);

            return response()->json(['data' => $document->fresh()], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/v1/employees/{employee}/documents/{document} — edit dokumen.
     */
    public function updateDocument(Request $request, Employee $employee, EmployeeDocument $document): JsonResponse
    {
        abort_unless($document->employee_id === $employee->id, 404, 'Dokumen tidak ditemukan.');

        $validated = $request->validate($this->documentRules());
        $document->update($validated);

        return response()->json(['data' => $document->fresh()]);
    }

    /**
     * DELETE /api/v1/employees/{employee}/documents/{document} — hapus dokumen.
     */
    public function destroyDocument(Employee $employee, EmployeeDocument $document): JsonResponse
    {
        abort_unless($document->employee_id === $employee->id, 404, 'Dokumen tidak ditemukan.');

        $document->delete();

        return response()->json(['message' => 'Dokumen dihapus.']);
    }

    private function documentRules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file_path' => ['nullable', 'string', 'max:255'],
            'file_name' => ['nullable', 'string', 'max:200'],
            'file_size' => ['nullable', 'string', 'max:50'],
            'mime_type' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'expiry_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'issued_by' => ['nullable', 'string', 'max:200'],
            'verification_status' => ['sometimes', Rule::in(['pending', 'verified', 'rejected'])],
        ];
    }
}
