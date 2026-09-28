<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    protected $fillable = [
        'first_name', 'last_name', 'date_of_birth', 'date_of_birth_unknown', 'gender',
        'mobile', 'email', 'blood_group', 'address', 'city', 'state', 'pincode',
        'emergency_contact_name', 'emergency_contact_mobile', 'status',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'immutable_date', 'date_of_birth_unknown' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(function (Patient $patient): void {
            if ($patient->isDirty(['id', 'hospital_id', 'branch_id', 'uhid', 'registered_by'])) {
                throw new LogicException('Patient identity and registration provenance cannot be changed.');
            }
        });
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where($query->qualifyColumn('hospital_id'), $hospitalId);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function medicalHistories(): HasMany
    {
        return $this->hasMany(PatientMedicalHistory::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }
}
