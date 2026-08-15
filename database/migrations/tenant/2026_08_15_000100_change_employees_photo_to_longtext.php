<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto profil dikirim sebagai data URI base64 terkompres (sama seperti
     * selfie_photo), ukurannya bisa jauh > 255 char dan bahkan > 64KB —
     * ubah ke LONGTEXT.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->longText('photo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('photo')->nullable()->change();
        });
    }
};
