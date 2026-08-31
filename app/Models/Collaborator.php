<?php

namespace App\Models;

use Database\Factories\CollaboratorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collaborator extends Model
{
    public const OCCUPATION_STUDENT = 'student';

    public const OCCUPATION_FULL_TIME = 'full_time';

    public const OCCUPATION_PART_TIME = 'part_time';

    /** @use HasFactory<CollaboratorFactory> */
    use HasFactory;

    protected $fillable = [
        'job_role_id',
        'full_name',
        'identity_document',
        'biometric_id',
        'occupation_status',
        'phone',
        'email',
        'address',
        'hire_date',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }

    public function employmentConditions(): HasMany
    {
        return $this->hasMany(EmploymentCondition::class)->orderByDesc('effective_from');
    }

    public function activityPeriods(): HasMany
    {
        return $this->hasMany(CollaboratorActivityPeriod::class)->orderBy('started_at');
    }

    public function currentEmploymentCondition(): ?EmploymentCondition
    {
        if ($this->relationLoaded('employmentConditions')) {
            return $this->employmentConditions->first(fn (EmploymentCondition $condition): bool => $condition->effective_from->lte(today())
                && ($condition->effective_to === null || $condition->effective_to->gte(today()))
            );
        }

        return $this->employmentConditions()
            ->whereDate('effective_from', '<=', today())
            ->where(function ($query): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today());
            })
            ->first();
    }

    public function getActiveDaysAttribute(): int
    {
        $periods = $this->relationLoaded('activityPeriods')
            ? $this->activityPeriods
            : $this->activityPeriods()->get();

        return $periods->sum(function (CollaboratorActivityPeriod $period): int {
            $end = $period->ended_at ?? today();

            return max(0, (int) $period->started_at->diffInDays($end) + 1);
        });
    }

    public function getSeniorityAttribute(): string
    {
        $days = $this->active_days;
        $years = intdiv($days, 365);
        $months = intdiv($days % 365, 30);
        $parts = [];

        if ($years > 0) {
            $parts[] = $years.' '.($years === 1 ? 'año' : 'años');
        }

        if ($months > 0 || $parts === []) {
            $parts[] = $months.' '.($months === 1 ? 'mes' : 'meses');
        }

        return implode(', ', $parts);
    }

    public function getOccupationStatusLabelAttribute(): ?string
    {
        return match ($this->occupation_status) {
            self::OCCUPATION_STUDENT => 'Estudiante',
            self::OCCUPATION_FULL_TIME => 'Jornada completa',
            self::OCCUPATION_PART_TIME => 'Jornada parcial',
            default => null,
        };
    }
}
