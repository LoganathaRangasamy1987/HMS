<?php

namespace App\Models;

use Database\Factories\LabResultValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LabResultValue extends Model
{
    /** @use HasFactory<LabResultValueFactory> */
    use HasFactory;

    protected $fillable = ['lab_result_id', 'lab_test_parameter_id', 'parameter_code', 'parameter_name', 'result_type', 'unit_symbol', 'reference_min', 'reference_max', 'reference_text', 'sort_order', 'numeric_value', 'text_value', 'boolean_value', 'flag'];

    protected function casts(): array
    {
        return ['reference_min' => 'decimal:4', 'reference_max' => 'decimal:4', 'numeric_value' => 'decimal:4', 'boolean_value' => 'boolean'];
    }

    protected static function booted(): void
    {
        $guard = function (self $value): void {
            if ($value->result()->where('status', '!=', 'DRAFT')->exists()) {
                throw new LogicException('Finalized laboratory values cannot be changed.');
            }
        };
        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(LabResult::class, 'lab_result_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(LabTestParameter::class, 'lab_test_parameter_id');
    }
}
