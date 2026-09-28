<?php

namespace App\Models;

use Database\Factories\DiagnosisCorrectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DiagnosisCorrection extends Model
{
    /** @use HasFactory<DiagnosisCorrectionFactory> */
    use HasFactory;

    protected $fillable = ['diagnosis_id', 'type', 'description', 'code_system', 'code', 'reason', 'corrected_by', 'corrected_at'];

    protected function casts(): array
    {
        return ['corrected_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Diagnosis corrections cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Diagnosis corrections cannot be deleted.'));
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
