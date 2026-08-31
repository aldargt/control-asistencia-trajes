<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('collaborators')->whereNull('biometric_id')->exists()) {
            throw new RuntimeException('Todos los colaboradores deben tener un ID biométrico antes de continuar.');
        }

        Schema::table('job_roles', function (Blueprint $table) {
            $table->decimal('reference_weekly_hours', 5, 2)->nullable()->after('description');
            $table->decimal('reference_monthly_salary', 12, 2)->nullable()->after('reference_weekly_hours');
        });

        Schema::table('collaborators', function (Blueprint $table) {
            $table->string('occupation_status', 50)->nullable()->after('biometric_id');
            $table->unsignedBigInteger('biometric_id')->nullable(false)->change();
        });

        Schema::table('employment_conditions', function (Blueprint $table) {
            $table->foreignId('job_role_id')->nullable()->after('collaborator_id')->constrained()->restrictOnDelete();
        });

        DB::table('employment_conditions')->orderBy('id')->each(function (object $condition): void {
            $jobRoleId = DB::table('collaborators')->where('id', $condition->collaborator_id)->value('job_role_id');
            DB::table('employment_conditions')->where('id', $condition->id)->update(['job_role_id' => $jobRoleId]);
        });

        Schema::table('employment_conditions', function (Blueprint $table) {
            $table->foreignId('job_role_id')->nullable(false)->change();
        });

        Schema::create('collaborator_activity_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaborator_id')->constrained()->restrictOnDelete();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['collaborator_id', 'started_at', 'ended_at'], 'activity_collaborator_dates_index');
        });

        DB::table('collaborators')->orderBy('id')->each(function (object $collaborator): void {
            $endedAt = null;

            if (! $collaborator->is_active) {
                $endedAt = Carbon::parse($collaborator->updated_at)->toDateString();
                $endedAt = max($endedAt, $collaborator->hire_date);
            }

            DB::table('collaborator_activity_periods')->insert([
                'collaborator_id' => $collaborator->id,
                'started_at' => $collaborator->hire_date,
                'ended_at' => $endedAt,
                'changed_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaborator_activity_periods');

        Schema::table('employment_conditions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_role_id');
        });

        Schema::table('collaborators', function (Blueprint $table) {
            $table->unsignedBigInteger('biometric_id')->nullable()->change();
            $table->dropColumn('occupation_status');
        });

        Schema::table('job_roles', function (Blueprint $table) {
            $table->dropColumn(['reference_weekly_hours', 'reference_monthly_salary']);
        });
    }
};
