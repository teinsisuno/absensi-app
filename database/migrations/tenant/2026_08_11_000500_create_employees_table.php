<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data kepegawaian. Akun login ada di tabel users — relasi lewat user_id
     * (terisi saat kode unik dari HR dipakai). Role mobile dari mobile_role.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->unique()->index(); // link ke akun (kode unik)
            $table->string('name');
            $table->string('photo')->nullable();
            $table->string('position')->nullable();
            $table->string('mobile_role')->default('karyawan'); // karyawan / supervisor / management
            $table->unsignedBigInteger('work_location_id')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->string('status')->default('active'); // active / inactive
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('work_location_id')->references('id')->on('work_locations')->nullOnDelete();
            $table->foreign('shift_id')->references('id')->on('shifts')->nullOnDelete();
            $table->foreign('supervisor_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
