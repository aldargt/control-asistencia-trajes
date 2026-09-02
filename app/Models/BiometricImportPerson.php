<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricImportPerson extends Model
{
    protected $fillable = ['collaborator_id', 'source_biometric_id', 'source_name', 'source_department', 'source_row', 'name_differs'];

    protected function casts(): array
    {
        return ['name_differs' => 'boolean'];
    }

    public function biometricImport(): BelongsTo
    {
        return $this->belongsTo(BiometricImport::class);
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(BiometricImportDay::class)->orderBy('mark_date');
    }
}
