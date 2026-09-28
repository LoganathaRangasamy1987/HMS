<?php

namespace App\Models;

use Database\Factories\DiagnosisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Diagnosis extends Model
{
    /** @use HasFactory<DiagnosisFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'patient_id', 'encounter_id', 'type', 'description', 'code_system', 'code', 'diagnosed_at', 'authored_by'];

    protected function casts(): array
    {
        return ['diagnosed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Diagnoses cannot be edited; create a correction.'));
        static::deleting(fn () => throw new LogicException('Diagnoses cannot be deleted.'));
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(DiagnosisCorrection::class)->orderBy('corrected_at');
    }
}
