<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_role_id')->constrained()->restrictOnDelete();
            $table->string('full_name');
            $table->string('identity_document')->nullable()->unique();
            $table->unsignedBigInteger('biometric_id')->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->date('hire_date');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaborators');
    }
};
