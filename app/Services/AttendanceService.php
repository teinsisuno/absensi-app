<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\WorkLocation;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Logika absen: validasi radius GPS (haversine), sesi clock in/out, riwayat karyawan.
 */
class AttendanceService
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    /**
     * Sesi clock in yang masih terbuka (record terakhir = clock_in, belum ada clock_out).
     */
    public function openSession(Employee $employee): ?Attendance
    {
        $latest = $employee->attendances()->latest('recorded_at')->first();

        return $latest?->type === 'clock_in' ? $latest : null;
    }

    /**
     * Clock in. Mode normal menolak kalau sesi masih terbuka (sudah clock in).
     * Mode force (clock in ulang) selalu menambah record — untuk riwayat tambahan
     * (mis. masuk lagi setelah clock out).
     */
    public function clockIn(Employee $employee, array $data, bool $force = false): Attendance
    {
        if (! $force && $this->openSession($employee)) {
            throw new InvalidArgumentException('Kamu sudah clock in. Lakukan clock out dulu.');
        }

        $location = $this->resolveLocation($employee, (float) $data['latitude'], (float) $data['longitude']);
        $distance = $this->distanceMeters(
            (float) $data['latitude'], (float) $data['longitude'],
            (float) $location->latitude, (float) $location->longitude
        );

        $this->assertWithinRadius($location, $distance);

        return Attendance::create([
            'employee_id' => $employee->id,
            'work_location_id' => $location->id,
            'type' => 'clock_in',
            'recorded_at' => now(),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'distance_meter' => round($distance, 2),
            'selfie_photo' => $data['selfie_photo'] ?? null,
            'face_verified' => $data['face_verified'] ?? false,
            'face_mode' => $data['face_mode'] ?? null,
            'face_confidence' => $data['face_confidence'] ?? null,
            'status' => 'valid',
        ]);
    }

    /**
     * Clock out. Mode normal menolak kalau tidak ada sesi terbuka.
     * Mode force (clock out ulang) selalu menambah record — untuk riwayat tambahan
     * (mis. catat pulang lagi setelah sebelumnya sudah clock out).
     */
    public function clockOut(Employee $employee, array $data, bool $force = false): Attendance
    {
        if (! $force && ! $this->openSession($employee)) {
            throw new InvalidArgumentException('Belum ada clock in — clock out dibatalkan.');
        }

        $location = $this->resolveLocation($employee, (float) $data['latitude'], (float) $data['longitude']);
        $distance = $this->distanceMeters(
            (float) $data['latitude'], (float) $data['longitude'],
            (float) $location->latitude, (float) $location->longitude
        );

        $this->assertWithinRadius($location, $distance);

        return Attendance::create([
            'employee_id' => $employee->id,
            'work_location_id' => $location->id,
            'type' => 'clock_out',
            'recorded_at' => now(),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'distance_meter' => round($distance, 2),
            'selfie_photo' => $data['selfie_photo'] ?? null,
            'face_verified' => $data['face_verified'] ?? false,
            'face_mode' => $data['face_mode'] ?? null,
            'face_confidence' => $data['face_confidence'] ?? null,
            'status' => 'valid',
        ]);
    }

    /**
     * Riwayat absen karyawan sendiri (opsional filter tanggal YYYY-MM-DD).
     */
    public function myHistory(Employee $employee, ?string $date): Collection
    {
        return $employee->attendances()
            ->with('workLocation')
            ->when($date, fn ($q) => $q->whereDate('recorded_at', $date))
            ->latest('recorded_at')
            ->get();
    }

    /**
     * Lokasi aktif yang dipakai: lokasi ter-assign karyawan kalau aktif,
     * selain itu lokasi aktif terdekat dari titik GPS karyawan.
     */
    private function resolveLocation(Employee $employee, float $latitude, float $longitude): WorkLocation
    {
        $locations = WorkLocation::where('is_active', true)->get();

        if ($locations->isEmpty()) {
            throw new InvalidArgumentException('Belum ada lokasi kerja aktif. Hubungi admin.');
        }

        if ($employee->work_location_id !== null) {
            $assigned = $locations->firstWhere('id', $employee->work_location_id);
            if ($assigned !== null) {
                return $assigned;
            }
        }

        return $locations
            ->sortBy(fn (WorkLocation $location) => $this->distanceMeters(
                $latitude, $longitude,
                (float) $location->latitude, (float) $location->longitude
            ))
            ->first();
    }

    private function assertWithinRadius(WorkLocation $location, float $distance): void
    {
        if ($distance > $location->radius_meter) {
            throw new InvalidArgumentException(
                'Lokasi kamu di luar radius kantor ('.round($distance).' m > radius '.$location->radius_meter.' m).'
            );
        }
    }

    /**
     * Jarak dua koordinat (meter) — formula haversine.
     */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
