<?php

namespace App\Models;

use Database\Factories\LabTestParameterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LabTestParameter extends Model
{
    /** @use HasFactory<LabTestParameterFactory> */
    use HasFactory;

    protected $fillable = ['lab_test_version_id', 'code', 'name', 'result_type', 'unit_id', 'reference_min', 'reference_max', 'reference_text', 'sort_order'];

    protected function casts(): array
    {
        return ['reference_min' => 'decimal:4', 'reference_max' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Versioned laboratory parameters cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Versioned laboratory parameters cannot be deleted.'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LabTestVersion::class, 'lab_test_version_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(LabUnit::class);
    }
}
