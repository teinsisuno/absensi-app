<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Semua yang bisa login per tenant: user web (superadmin/hr via SSO Central)
     * dan user mobile hasil registrasi mandiri (role employee, ter-link ke karyawan
     * via kode unik). PIN 4-6 digit untuk login cepat karyawan.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_user_id')->nullable()->index();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password_hash')->nullable(); // registrasi mandiri; null = user SSO Central
            $table->string('pin_hash')->nullable();      // PIN 4-6 digit (login cepat)
            $table->string('role')->default('employee'); // superadmin / hr / employee
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
