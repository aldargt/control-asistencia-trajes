<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCalculationInterval extends Model
{
    protected $fillable = ['started_at', 'ended_at', 'minutes', 'sequence'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'minutes' => 'integer'];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(AttendanceCalculationDay::class, 'attendance_calculation_day_id');
    }
}
