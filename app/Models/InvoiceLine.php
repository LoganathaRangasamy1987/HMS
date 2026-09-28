<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InvoiceLine extends Model
{
    protected $fillable = ['service_item_id', 'service_code', 'description', 'quantity', 'unit_price', 'discount_type', 'discount_value', 'tax_rate_percent', 'subtotal', 'discount_amount', 'tax_amount', 'total'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'discount_value' => 'decimal:2', 'tax_rate_percent' => 'decimal:2', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        $guard = function (InvoiceLine $line): void {
            if ($line->invoice()->where('status', '!=', 'DRAFT')->exists()) {
                throw new LogicException('Issued invoice lines cannot be changed.');
            }
        };
        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
