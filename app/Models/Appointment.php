<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'patient_id', 'doctor_profile_id', 'appointment_date', 'starts_at', 'ends_at', 'token_number', 'type', 'status', 'reason', 'cancellation_reason', 'request_key', 'created_by'];

    protected function casts(): array
    {
        return ['appointment_date' => 'immutable_date'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctorProfile(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function encounter(): HasOne
    {
        return $this->hasOne(Encounter::class);
    }
}
