<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biometric_import_person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collaborator_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 30);
            $table->string('balance_status', 20)->nullable();
            $table->unsignedInteger('expected_minutes')->nullable();
            $table->unsignedInteger('recognized_minutes')->default(0);
            $table->integer('difference_minutes')->nullable();
            $table->unsignedSmallInteger('pending_days')->default(0);
            $table->unsignedSmallInteger('no_marks_days')->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['control_period_id', 'biometric_import_person_id'], 'calculation_period_person_unique');
        });

        Schema::create('attendance_calculation_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_interpretation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_correction_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->string('status', 30);
            $table->string('source_type', 20)->nullable();
            $table->unsignedInteger('recognized_minutes')->nullable();
            $table->timestamps();

            $table->unique(['attendance_calculation_id', 'attendance_interpretation_id'], 'calculation_day_unique');
        });

        Schema::create('attendance_calculation_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_calculation_day_id');
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedInteger('minutes');
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['attendance_calculation_day_id', 'sequence'], 'calculation_interval_sequence_unique');
            $table->foreign('attendance_calculation_day_id', 'calculation_interval_day_fk')->references('id')->on('attendance_calculation_days')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_calculation_intervals');
        Schema::dropIfExists('attendance_calculation_days');
        Schema::dropIfExists('attendance_calculations');
    }
};
