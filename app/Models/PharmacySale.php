<?php

namespace App\Models;

use Database\Factories\PharmacySaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PharmacySale extends Model
{
    /** @use HasFactory<PharmacySaleFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'patient_id', 'prescription_id', 'invoice_id', 'number', 'status', 'currency', 'subtotal', 'tax_amount', 'total', 'request_key', 'payload_hash', 'dispensed_by', 'dispensed_at'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2', 'dispensed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Dispensed sales cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Dispensed sales cannot be deleted.'));
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PharmacySaleItem::class);
    }
}
