<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceInterpretation extends Model
{
    public const STATUS_COMPLETE = 'complete';

    public const STATUS_REQUIRES_REVIEW = 'requires_review';

    public const STATUS_NO_MARKS = 'no_marks';

    protected $fillable = ['work_date', 'status', 'original_marks_count', 'logical_marks_count', 'duplicate_marks_count', 'interpreted_at'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'interpreted_at' => 'datetime'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(BiometricImportPerson::class, 'biometric_import_person_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(InterpretedMark::class)->orderBy('sequence');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class, 'biometric_import_person_id', 'biometric_import_person_id');
    }

    public function activeCorrection(): ?AttendanceCorrection
    {
        $query = $this->corrections()
            ->whereDate('work_date', $this->work_date)
            ->whereNull('superseded_at')
            ->whereNull('undone_at')
            ->with('marks')
            ->latest('corrected_at');

        return $query->first();
    }
}
