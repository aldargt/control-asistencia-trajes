<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_import_person_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('automatic_status', 30);
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('corrected_at');
            $table->text('notes')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['biometric_import_person_id', 'work_date'], 'correction_person_date_index');
        });

        Schema::create('attendance_correction_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_correction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interpreted_mark_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('biometric_mark_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->unsignedSmallInteger('sequence');
            $table->string('source_type', 20);
            $table->foreignId('added_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['attendance_correction_id', 'sequence'], 'correction_mark_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_marks');
        Schema::dropIfExists('attendance_corrections');
    }
};
