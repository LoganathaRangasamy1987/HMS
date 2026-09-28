<?php

namespace App\Models;

use Database\Factories\LabOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class LabOrder extends Model
{
    /** @use HasFactory<LabOrderFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'patient_id', 'encounter_id', 'doctor_profile_id', 'invoice_id', 'number', 'status', 'clinical_notes', 'ordered_by', 'ordered_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['ordered_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $order): void {
            if ($order->isDirty(['hospital_id', 'branch_id', 'patient_id', 'encounter_id', 'doctor_profile_id', 'invoice_id', 'number', 'clinical_notes', 'ordered_by', 'ordered_at'])) {
                throw new LogicException('Laboratory order provenance cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Laboratory orders cannot be deleted.'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function specimens(): HasMany
    {
        return $this->hasMany(LabSpecimen::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function doctorProfile(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
