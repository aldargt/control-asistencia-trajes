<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_correction_mark_sources', function (Blueprint $table) {
            $table->foreignId('attendance_correction_mark_id');
            $table->foreignId('biometric_mark_id');
            $table->unsignedSmallInteger('sequence');

            $table->primary(['attendance_correction_mark_id', 'biometric_mark_id'], 'correction_mark_source_primary');
            $table->foreign('attendance_correction_mark_id', 'correction_mark_source_correction_fk')->references('id')->on('attendance_correction_marks')->cascadeOnDelete();
            $table->foreign('biometric_mark_id', 'correction_mark_source_biometric_fk')->references('id')->on('biometric_marks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_mark_sources');
    }
};
