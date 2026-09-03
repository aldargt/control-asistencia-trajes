<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InterpretedMark extends Model
{
    public const TYPE_ENTRY = 'entry';

    public const TYPE_EXIT = 'exit';

    protected $fillable = ['representative_biometric_mark_id', 'occurred_at', 'sequence', 'type', 'source_marks_count', 'assigned_from_early_morning'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'assigned_from_early_morning' => 'boolean'];
    }

    public function interpretation(): BelongsTo
    {
        return $this->belongsTo(AttendanceInterpretation::class, 'attendance_interpretation_id');
    }

    public function representativeSource(): BelongsTo
    {
        return $this->belongsTo(BiometricMark::class, 'representative_biometric_mark_id');
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(BiometricMark::class, 'interpreted_mark_sources')
            ->withPivot('sequence')
            ->orderByPivot('sequence');
    }
}
