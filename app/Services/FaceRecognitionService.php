<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FaceTemplate;
use InvalidArgumentException;

/**
 * Enroll & verifikasi wajah karyawan.
 * Template disimpan di server (face-api.js embedding JSON).
 * Dua mode: client (matching di device) & server (matching di sini).
 */
class FaceRecognitionService
{
    /** Ambang jarak Euclidean untuk dianggap cocok (semakin kecil = semakin ketat). */
    public const THRESHOLD = 0.6;

    /**
     * Enroll wajah karyawan — simpan template embedding dari face-api.js.
     * Satu karyawan = satu template (replace kalau enroll ulang).
     */
    public function enroll(Employee $employee, string $template, string $mode = 'server'): FaceTemplate
    {
        if (! in_array($mode, ['client', 'server'], true)) {
            throw new InvalidArgumentException('Mode harus client atau server.');
        }

        $decoded = json_decode($template, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Template wajah tidak valid (harus JSON array embedding).');
        }

        return FaceTemplate::updateOrCreate(
            ['employee_id' => $employee->id],
            ['template' => $template, 'mode' => $mode]
        );
    }

    /**
     * Verifikasi wajah saat clock in/out — cocokkan dengan template tersimpan.
     * Return array: [match => bool, confidence => float, distance => float, mode => string]
     */
    public function verify(Employee $employee, string $faceDescriptor): array
    {
        $template = FaceTemplate::where('employee_id', $employee->id)->first();

        if (! $template) {
            throw new InvalidArgumentException('Template wajah belum terdaftar. Lakukan enroll dulu.');
        }

        $stored = json_decode($template->template, true);
        $input = json_decode($faceDescriptor, true);

        if (! is_array($stored) || ! is_array($input)) {
            throw new InvalidArgumentException('Format descriptor wajah tidak valid.');
        }

        $distance = $this->euclideanDistance($stored, $input);
        $confidence = max(0, 1 - $distance);

        return [
            'match' => $distance < self::THRESHOLD,
            'confidence' => round($confidence, 4),
            'distance' => round($distance, 4),
            'mode' => $template->mode,
        ];
    }

    private function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException('Dimensi descriptor tidak cocok.');
        }

        $sum = 0.0;
        foreach ($a as $i => $val) {
            $sum += ($val - $b[$i]) ** 2;
        }

        return sqrt($sum);
    }

    public function isEnrolled(Employee $employee): bool
    {
        return FaceTemplate::where('employee_id', $employee->id)->exists();
    }
}
