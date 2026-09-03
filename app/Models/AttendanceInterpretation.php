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
}
