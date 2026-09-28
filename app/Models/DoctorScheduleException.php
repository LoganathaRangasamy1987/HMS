<?php

namespace App\Models;

use Database\Factories\DoctorScheduleExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorScheduleException extends Model
{
    /** @use HasFactory<DoctorScheduleExceptionFactory> */
    use HasFactory;

    protected $fillable = ['doctor_profile_id', 'date', 'availability', 'starts_at', 'ends_at', 'slot_duration_minutes', 'capacity_per_slot', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }
}
