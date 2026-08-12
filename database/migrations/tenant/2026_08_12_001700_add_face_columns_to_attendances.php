<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom hasil verifikasi wajah di record absen.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('face_verified')->default(false)->after('selfie_photo');
            $table->string('face_mode')->nullable()->after('face_verified');
            $table->decimal('face_confidence', 5, 4)->nullable()->after('face_mode');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['face_verified', 'face_mode', 'face_confidence']);
        });
    }
};
