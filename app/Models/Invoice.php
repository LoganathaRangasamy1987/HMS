<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'patient_id', 'appointment_id', 'created_by'];

    protected $attributes = ['status' => 'DRAFT', 'currency' => 'INR', 'subtotal' => '0.00', 'discount_total' => '0.00', 'tax_total' => '0.00', 'total' => '0.00'];

    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime', 'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            if ($invoice->getOriginal('status') !== 'DRAFT' && $invoice->isDirty(['hospital_id', 'branch_id', 'patient_id', 'appointment_id', 'number', 'currency', 'subtotal', 'discount_total', 'tax_total', 'total', 'created_by', 'issued_by', 'issued_at'])) {
                throw new LogicException('Issued invoice charges and identity cannot be changed.');
            }
            if ($invoice->isDirty(['hospital_id', 'branch_id', 'created_by'])) {
                throw new LogicException('Invoice provenance cannot be changed.');
            }
        });
        static::deleting(function (Invoice $invoice): void {
            if ($invoice->status !== 'DRAFT') {
                throw new LogicException('Issued invoices cannot be deleted.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function financialAdjustments(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function labOrder(): HasOne
    {
        return $this->hasOne(LabOrder::class);
    }

    public function pharmacySale(): HasOne
    {
        return $this->hasOne(PharmacySale::class);
    }
}
