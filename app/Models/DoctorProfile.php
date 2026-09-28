<?php

namespace App\Models;

use Database\Factories\DoctorProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorProfile extends Model
{
    /** @use HasFactory<DoctorProfileFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'user_id', 'department_id', 'registration_number', 'qualification', 'specialization', 'consultation_fee', 'status'];

    protected function casts(): array
    {
        return ['consultation_fee' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function unavailabilities(): HasMany
    {
        return $this->hasMany(DoctorUnavailability::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(DoctorScheduleException::class);
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
