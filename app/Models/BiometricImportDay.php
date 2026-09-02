<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricImportDay extends Model
{
    protected $fillable = ['mark_date', 'original_value', 'extraction_warning'];

    protected function casts(): array
    {
        return ['mark_date' => 'date', 'extraction_warning' => 'boolean'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(BiometricImportPerson::class, 'biometric_import_person_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(BiometricMark::class)->orderBy('sequence');
    }
}
