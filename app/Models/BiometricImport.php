<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricImport extends Model
{
    protected $fillable = ['control_period_id', 'imported_by', 'original_filename', 'stored_path', 'mime_type', 'file_size', 'sha256', 'people_count', 'matched_people_count', 'unmatched_people_count', 'mark_count', 'warning_count', 'imported_at'];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }

    public function controlPeriod(): BelongsTo
    {
        return $this->belongsTo(ControlPeriod::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function people(): HasMany
    {
        return $this->hasMany(BiometricImportPerson::class)->orderBy('source_row');
    }
}
