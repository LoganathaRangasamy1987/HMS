<?php

namespace App\Models;

use Database\Factories\VitalObservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class VitalObservation extends Model
{
    /** @use HasFactory<VitalObservationFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'patient_id', 'encounter_id', 'temperature', 'temperature_unit', 'pulse', 'pulse_unit', 'respiratory_rate', 'respiratory_rate_unit', 'systolic_bp', 'diastolic_bp', 'blood_pressure_unit', 'oxygen_saturation', 'oxygen_saturation_unit', 'weight', 'weight_unit', 'height', 'height_unit', 'notes', 'measured_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['temperature' => 'decimal:2', 'oxygen_saturation' => 'decimal:2', 'weight' => 'decimal:2', 'height' => 'decimal:2', 'measured_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Vital observations cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Vital observations cannot be deleted.'));
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
