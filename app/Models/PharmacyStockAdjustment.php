<?php

namespace App\Models;

use Database\Factories\PharmacyStockAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PharmacyStockAdjustment extends Model
{
    /** @use HasFactory<PharmacyStockAdjustmentFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'branch_id', 'medicine_id', 'medicine_batch_id', 'pharmacy_sale_item_id', 'pharmacy_purchase_item_id', 'stock_movement_id', 'type', 'quantity', 'reason', 'status', 'request_key', 'payload_hash', 'requested_by', 'requested_at', 'decided_by', 'decided_at', 'decision_reason'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'requested_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $adjustment): void {
            if ($adjustment->getOriginal('status') !== 'PENDING' || $adjustment->isDirty(['hospital_id', 'branch_id', 'medicine_id', 'medicine_batch_id', 'pharmacy_sale_item_id', 'pharmacy_purchase_item_id', 'type', 'quantity', 'reason', 'request_key', 'payload_hash', 'requested_by', 'requested_at'])) {
                throw new LogicException('Stock adjustment requests and decisions cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Stock adjustments cannot be deleted.'));
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PharmacySaleItem::class, 'pharmacy_sale_item_id');
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PharmacyPurchaseItem::class, 'pharmacy_purchase_item_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
