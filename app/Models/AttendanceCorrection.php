<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCorrection extends Model
{
    protected $fillable = ['biometric_import_person_id', 'work_date', 'automatic_status', 'corrected_by', 'corrected_at', 'notes', 'superseded_at', 'superseded_by', 'undone_at', 'undone_by'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'corrected_at' => 'datetime', 'superseded_at' => 'datetime', 'undone_at' => 'datetime'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(BiometricImportPerson::class, 'biometric_import_person_id');
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionMark::class)->orderBy('sequence');
    }
}
