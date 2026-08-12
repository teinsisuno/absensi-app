<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * selfie_photo sekarang menyimpan data URL gambar terkompres (base64),
     * ukurannya bisa jauh > 255 char — ubah ke LONGTEXT.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->longText('selfie_photo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('selfie_photo')->nullable()->change();
        });
    }
};
