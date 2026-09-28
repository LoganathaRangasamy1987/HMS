<?php

namespace App\Models;

use Database\Factories\PharmacyPurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PharmacyPurchase extends Model
{
    /** @use HasFactory<PharmacyPurchaseFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'pharmacy_supplier_id', 'number', 'supplier_invoice_number', 'purchase_date', 'status', 'currency', 'subtotal', 'tax_amount', 'total', 'request_key', 'payload_hash', 'received_by', 'received_at'];

    protected function casts(): array
    {
        return ['purchase_date' => 'immutable_date', 'received_at' => 'immutable_datetime', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Received purchases cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Received purchases cannot be deleted.'));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(PharmacySupplier::class, 'pharmacy_supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PharmacyPurchaseItem::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
