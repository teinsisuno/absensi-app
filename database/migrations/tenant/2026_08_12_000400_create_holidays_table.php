<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hari libur — milik satu kalender kerja (nasional / company).
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('working_calendar_id')->nullable()->constrained('working_calendars')->cascadeOnDelete();
            $table->date('date');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->default('nasional'); // nasional / company
            $table->boolean('is_national_holiday')->default(false);
            $table->boolean('is_company_holiday')->default(false);
            $table->string('external_code')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
