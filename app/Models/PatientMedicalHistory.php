<?php

namespace App\Models;

use Database\Factories\PatientMedicalHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientMedicalHistory extends Model
{
    /** @use HasFactory<PatientMedicalHistoryFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'patient_id', 'branch_id', 'recorded_by', 'condition', 'onset_date', 'onset_date_unknown', 'status', 'notes'];

    protected function casts(): array
    {
        return ['onset_date' => 'immutable_date', 'onset_date_unknown' => 'boolean'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
