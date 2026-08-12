<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot many-to-many group ↔ karyawan.
     */
    public function up(): void
    {
        Schema::create('employee_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('employee_groups')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_group_members');
    }
};
