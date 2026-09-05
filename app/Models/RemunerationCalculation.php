<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationCalculation extends Model
{
    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_CONFIGURATION_PENDING = 'configuration_pending';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'daily_reference_hours' => 'decimal:6', 'hourly_rate' => 'decimal:10',
            'monthly_salary_cents' => 'integer', 'weekly_hours_hundredths' => 'integer',
            'reference_days' => 'integer', 'expected_minutes' => 'integer', 'recognized_minutes' => 'integer',
            'difference_minutes' => 'integer', 'deficit_minutes' => 'integer', 'surplus_minutes' => 'integer',
            'valued_duration_hundredths' => 'integer', 'base_amount_cents' => 'integer',
            'deficit_deduction_cents' => 'integer', 'surplus_increment_cents' => 'integer',
            'final_amount_cents' => 'integer', 'source_attendance_calculated_at' => 'datetime',
            'source_condition_updated_at' => 'datetime', 'calculated_at' => 'datetime',
        ];
    }

    public function attendanceCalculation(): BelongsTo
    {
        return $this->belongsTo(AttendanceCalculation::class);
    }

    public function controlPeriod(): BelongsTo
    {
        return $this->belongsTo(ControlPeriod::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(BiometricImportPerson::class, 'biometric_import_person_id');
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function employmentCondition(): BelongsTo
    {
        return $this->belongsTo(EmploymentCondition::class);
    }
}
