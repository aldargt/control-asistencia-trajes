<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_period_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->unsignedInteger('people_count')->default(0);
            $table->unsignedInteger('matched_people_count')->default(0);
            $table->unsignedInteger('unmatched_people_count')->default(0);
            $table->unsignedInteger('mark_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->timestamp('imported_at');
            $table->timestamps();
        });

        Schema::create('biometric_import_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collaborator_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source_biometric_id', 100);
            $table->string('source_name');
            $table->string('source_department')->nullable();
            $table->unsignedInteger('source_row');
            $table->boolean('name_differs')->default(false);
            $table->timestamps();

            $table->unique(['biometric_import_id', 'source_biometric_id'], 'import_person_biometric_unique');
        });

        Schema::create('biometric_import_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_import_person_id')->constrained()->cascadeOnDelete();
            $table->date('mark_date');
            $table->text('original_value')->nullable();
            $table->boolean('extraction_warning')->default(false);
            $table->timestamps();

            $table->unique(['biometric_import_person_id', 'mark_date'], 'import_person_date_unique');
        });

        Schema::create('biometric_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_import_day_id')->constrained()->cascadeOnDelete();
            $table->time('marked_time');
            $table->unsignedSmallInteger('sequence');
            $table->string('source_text', 8);
            $table->timestamps();

            $table->unique(['biometric_import_day_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_marks');
        Schema::dropIfExists('biometric_import_days');
        Schema::dropIfExists('biometric_import_people');
        Schema::dropIfExists('biometric_imports');
    }
};
