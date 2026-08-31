<?php

namespace App\Models;

use Database\Factories\JobRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobRole extends Model
{
    /** @use HasFactory<JobRoleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'reference_weekly_hours',
        'reference_monthly_salary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reference_weekly_hours' => 'decimal:2',
            'reference_monthly_salary' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(Collaborator::class);
    }

    public function employmentConditions(): HasMany
    {
        return $this->hasMany(EmploymentCondition::class);
    }

    public function estimateMonthlySalary(float $weeklyHours): ?float
    {
        if (! $this->reference_weekly_hours || ! $this->reference_monthly_salary) {
            return null;
        }

        return round(((float) $this->reference_monthly_salary / (float) $this->reference_weekly_hours) * $weeklyHours, 2);
    }
}
