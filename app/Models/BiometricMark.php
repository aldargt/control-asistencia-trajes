<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricMark extends Model
{
    protected $fillable = ['marked_time', 'sequence', 'source_text'];

    public function day(): BelongsTo
    {
        return $this->belongsTo(BiometricImportDay::class, 'biometric_import_day_id');
    }
}
