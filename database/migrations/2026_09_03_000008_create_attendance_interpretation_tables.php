<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_interpretations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_import_person_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('status', 30);
            $table->unsignedSmallInteger('original_marks_count')->default(0);
            $table->unsignedSmallInteger('logical_marks_count')->default(0);
            $table->unsignedSmallInteger('duplicate_marks_count')->default(0);
            $table->timestamp('interpreted_at');
            $table->timestamps();

            $table->unique(['biometric_import_person_id', 'work_date'], 'interpretation_person_date_unique');
        });

        Schema::create('interpreted_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_interpretation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('representative_biometric_mark_id')->constrained('biometric_marks')->cascadeOnDelete();
            $table->dateTime('occurred_at');
            $table->unsignedSmallInteger('sequence');
            $table->string('type', 20)->nullable();
            $table->unsignedSmallInteger('source_marks_count')->default(1);
            $table->boolean('assigned_from_early_morning')->default(false);
            $table->timestamps();

            $table->unique(['attendance_interpretation_id', 'sequence'], 'interpretation_mark_sequence_unique');
        });

        Schema::create('interpreted_mark_sources', function (Blueprint $table) {
            $table->foreignId('interpreted_mark_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biometric_mark_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');

            $table->primary(['interpreted_mark_id', 'biometric_mark_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interpreted_mark_sources');
        Schema::dropIfExists('interpreted_marks');
        Schema::dropIfExists('attendance_interpretations');
    }
};
