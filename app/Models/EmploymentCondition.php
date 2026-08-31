<?php

namespace App\Models;

use Database\Factories\EmploymentConditionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentCondition extends Model
{
    /** @use HasFactory<EmploymentConditionFactory> */
    use HasFactory;

    protected $fillable = [
        'collaborator_id',
        'job_role_id',
        'monthly_salary',
        'weekly_hours',
        'effective_from',
        'effective_to',
        'reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'weekly_hours' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
