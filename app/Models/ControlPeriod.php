<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class ControlPeriod extends Model
{
    protected $fillable = ['year', 'month', 'reference_days', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'reference_days' => 'integer'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function biometricImport(): HasOne
    {
        return $this->hasOne(BiometricImport::class);
    }

    public function getStartsAtAttribute(): Carbon
    {
        return Carbon::create($this->year, $this->month)->startOfMonth();
    }

    public function getEndsAtAttribute(): Carbon
    {
        return $this->starts_at->copy()->endOfMonth();
    }

    public function getNameAttribute(): string
    {
        return ucfirst($this->starts_at->locale('es')->translatedFormat('F Y'));
    }

    public function hourReferenceFor(Collaborator $collaborator): array
    {
        $conditions = $collaborator->relationLoaded('employmentConditions')
            ? $collaborator->employmentConditions
            : $collaborator->employmentConditions()->get();

        $applicable = $conditions->filter(fn (EmploymentCondition $condition): bool => $condition->effective_from->lte($this->ends_at)
            && ($condition->effective_to === null || $condition->effective_to->gte($this->starts_at))
        )->values();

        if ($applicable->count() !== 1) {
            return [
                'status' => $applicable->isEmpty() ? 'without_condition' : 'multiple_conditions',
                'condition' => null,
                'weekly_hours' => null,
                'daily_reference_hours' => null,
                'expected_hours' => null,
            ];
        }

        $condition = $applicable->first();
        $weeklyHours = (float) $condition->weekly_hours;
        $weeklyReferenceDays = config('hour-control.weekly_reference_days');

        return [
            'status' => 'calculated',
            'condition' => $condition,
            'weekly_hours' => $weeklyHours,
            'daily_reference_hours' => $weeklyHours / $weeklyReferenceDays,
            'expected_hours' => ($weeklyHours * $this->reference_days) / $weeklyReferenceDays,
        ];
    }
}
