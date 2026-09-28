<?php

namespace App\Models;

use Database\Factories\EncounterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Encounter extends Model
{
    /** @use HasFactory<EncounterFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'patient_id', 'appointment_id', 'doctor_profile_id', 'department_id', 'encounter_type', 'status', 'opened_at', 'opened_by', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return ['opened_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (Encounter $encounter): void {
            if ($encounter->isDirty(['hospital_id', 'branch_id', 'patient_id', 'appointment_id', 'doctor_profile_id', 'department_id', 'encounter_type', 'opened_at', 'opened_by'])) {
                throw new LogicException('Encounter ownership and provenance cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Encounters cannot be deleted.'));
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctorProfile(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    public function vitalObservations(): HasMany
    {
        return $this->hasMany(VitalObservation::class)->orderByDesc('measured_at');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class)->orderBy('diagnosed_at');
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }
}
