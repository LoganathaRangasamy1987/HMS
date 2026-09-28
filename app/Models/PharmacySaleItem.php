<?php

namespace App\Models;

use Database\Factories\PharmacySaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PharmacySaleItem extends Model
{
    /** @use HasFactory<PharmacySaleItemFactory> */
    use HasFactory;

    protected $fillable = ['pharmacy_sale_id', 'prescription_item_id', 'medicine_id', 'medicine_batch_id', 'invoice_line_id', 'medicine_code', 'medicine_name', 'batch_number', 'expires_on', 'quantity', 'unit_price', 'tax_rate_percent', 'subtotal', 'tax_amount', 'total'];

    protected function casts(): array
    {
        return ['expires_on' => 'immutable_date', 'quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'tax_rate_percent' => 'decimal:2', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Dispensed sale items cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Dispensed sale items cannot be deleted.'));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PharmacySale::class, 'pharmacy_sale_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }
}
