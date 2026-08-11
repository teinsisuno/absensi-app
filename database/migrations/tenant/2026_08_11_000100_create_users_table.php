<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin/owner/supervisor per tenant DB (dibuat via SSO dari Central).
     * Karyawan ada di tabel employees (login PIN), bukan di sini.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_user_id')->nullable()->index();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('admin'); // owner / admin / supervisor
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
