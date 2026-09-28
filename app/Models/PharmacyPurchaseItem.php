<?php

namespace App\Models;

use Database\Factories\PharmacyPurchaseItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PharmacyPurchaseItem extends Model
{
    /** @use HasFactory<PharmacyPurchaseItemFactory> */
    use HasFactory;

    protected $fillable = ['pharmacy_purchase_id', 'medicine_id', 'medicine_batch_id', 'medicine_code', 'medicine_name', 'batch_number', 'manufactured_on', 'expires_on', 'unit_symbol', 'quantity', 'free_quantity', 'unit_cost', 'sale_price', 'tax_rate_percent', 'subtotal', 'tax_amount', 'total'];

    protected function casts(): array
    {
        return ['manufactured_on' => 'immutable_date', 'expires_on' => 'immutable_date', 'quantity' => 'decimal:3', 'free_quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'sale_price' => 'decimal:2', 'tax_rate_percent' => 'decimal:2', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Receipt items cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Receipt items cannot be deleted.'));
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PharmacyPurchase::class, 'pharmacy_purchase_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }
}
