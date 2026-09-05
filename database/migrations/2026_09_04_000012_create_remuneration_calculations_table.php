<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remuneration_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_calculation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('control_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biometric_import_person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collaborator_id')->constrained()->restrictOnDelete();
            $table->foreignId('employment_condition_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 30);
            $table->unsignedBigInteger('monthly_salary_cents')->nullable();
            $table->unsignedInteger('weekly_hours_hundredths')->nullable();
            $table->unsignedTinyInteger('reference_days')->nullable();
            $table->decimal('daily_reference_hours', 12, 6)->nullable();
            $table->unsignedInteger('expected_minutes')->nullable();
            $table->unsignedInteger('recognized_minutes')->nullable();
            $table->integer('difference_minutes')->nullable();
            $table->unsignedInteger('deficit_minutes')->default(0);
            $table->unsignedInteger('surplus_minutes')->default(0);
            $table->unsignedInteger('valued_duration_hundredths')->default(0);
            $table->decimal('hourly_rate', 20, 10)->nullable();
            $table->unsignedBigInteger('base_amount_cents')->nullable();
            $table->unsignedBigInteger('deficit_deduction_cents')->default(0);
            $table->unsignedBigInteger('surplus_increment_cents')->default(0);
            $table->unsignedBigInteger('final_amount_cents')->nullable();
            $table->timestamp('source_attendance_calculated_at')->nullable();
            $table->timestamp('source_condition_updated_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['control_period_id', 'collaborator_id'], 'remuneration_period_collaborator_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remuneration_calculations');
    }
};
