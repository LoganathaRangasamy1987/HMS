<?php

namespace App\Models;

use Database\Factories\PatientAllergyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAllergy extends Model
{
    /** @use HasFactory<PatientAllergyFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'patient_id', 'branch_id', 'recorded_by', 'allergen', 'reaction', 'severity', 'status', 'notes'];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
