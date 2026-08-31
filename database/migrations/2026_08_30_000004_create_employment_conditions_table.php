<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaborator_id')->constrained()->restrictOnDelete();
            $table->decimal('monthly_salary', 12, 2);
            $table->decimal('weekly_hours', 5, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['collaborator_id', 'effective_from']);
            $table->index(
                ['collaborator_id', 'effective_from', 'effective_to'],
                'conditions_collaborator_vigency_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_conditions');
    }
};
