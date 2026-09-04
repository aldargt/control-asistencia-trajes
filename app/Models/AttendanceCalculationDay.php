<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCalculationDay extends Model
{
    public const STATUS_RECOGNIZED = 'recognized';

    public const STATUS_PENDING = 'pending';

    public const STATUS_NO_MARKS = 'no_marks';

    public const SOURCE_AUTOMATIC = 'automatic';

    public const SOURCE_CORRECTION = 'correction';

    protected $fillable = ['attendance_interpretation_id', 'attendance_correction_id', 'work_date', 'status', 'source_type', 'recognized_minutes'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'recognized_minutes' => 'integer'];
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(AttendanceCalculation::class, 'attendance_calculation_id');
    }

    public function interpretation(): BelongsTo
    {
        return $this->belongsTo(AttendanceInterpretation::class);
    }

    public function correction(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrection::class);
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(AttendanceCalculationInterval::class)->orderBy('sequence');
    }
}
