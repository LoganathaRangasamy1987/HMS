<?php

namespace App\Models;

use Database\Factories\DoctorScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{
    /** @use HasFactory<DoctorScheduleFactory> */
    use HasFactory;

    protected $fillable = ['doctor_profile_id', 'day_of_week', 'starts_at', 'ends_at', 'slot_duration_minutes', 'capacity_per_slot', 'status'];
}
