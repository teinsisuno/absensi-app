<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record absen (clock in/out) dengan GPS + selfie.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('work_location_id')->nullable();
            $table->string('type'); // clock_in / clock_out
            $table->dateTime('recorded_at'); // waktu server
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('distance_meter', 10, 2)->nullable();
            $table->string('selfie_photo')->nullable();
            $table->string('status')->default('valid'); // valid / out_of_radius_approved / flagged
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('work_location_id')->references('id')->on('work_locations')->nullOnDelete();
            $table->index(['employee_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
