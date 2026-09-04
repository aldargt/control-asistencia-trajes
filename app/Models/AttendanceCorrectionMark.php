<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttendanceCorrectionMark extends Model
{
    public const SOURCE_BIOMETRIC = 'biometric';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = ['interpreted_mark_id', 'biometric_mark_id', 'occurred_at', 'sequence', 'source_type', 'added_by'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function correction(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrection::class, 'attendance_correction_id');
    }

    public function biometricSources(): BelongsToMany
    {
        return $this->belongsToMany(BiometricMark::class, 'attendance_correction_mark_sources')
            ->withPivot('sequence')
            ->orderByPivot('sequence');
    }
}
