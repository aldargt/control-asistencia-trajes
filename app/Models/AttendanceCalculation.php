<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCalculation extends Model
{
    public const STATUS_COMPLETE = 'complete';

    public const STATUS_PROVISIONAL = 'provisional';

    public const STATUS_CONFIGURATION_PENDING = 'configuration_pending';

    public const BALANCE_COMPLIANCE = 'compliance';

    public const BALANCE_DEFICIT = 'deficit';

    public const BALANCE_SURPLUS = 'surplus';

    protected $fillable = ['control_period_id', 'biometric_import_person_id', 'collaborator_id', 'status', 'balance_status', 'expected_minutes', 'recognized_minutes', 'difference_minutes', 'pending_days', 'no_marks_days', 'calculated_at'];

    protected function casts(): array
    {
        return ['expected_minutes' => 'integer', 'recognized_minutes' => 'integer', 'difference_minutes' => 'integer', 'pending_days' => 'integer', 'no_marks_days' => 'integer', 'calculated_at' => 'datetime'];
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

    public function days(): HasMany
    {
        return $this->hasMany(AttendanceCalculationDay::class)->orderBy('work_date');
    }
}
