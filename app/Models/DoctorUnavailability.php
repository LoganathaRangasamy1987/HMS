<?php

namespace App\Models;

use Database\Factories\DoctorUnavailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorUnavailability extends Model
{
    /** @use HasFactory<DoctorUnavailabilityFactory> */
    use HasFactory;

    protected $fillable = ['doctor_profile_id', 'type', 'starts_on', 'ends_on', 'reason'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }
}
