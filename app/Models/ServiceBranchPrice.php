<?php

namespace App\Models;

use Database\Factories\ServiceBranchPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBranchPrice extends Model
{
    /** @use HasFactory<ServiceBranchPriceFactory> */
    use HasFactory;

    protected $fillable = ['service_item_id', 'branch_id', 'base_price', 'tax_rate_percent', 'discount_type', 'discount_value', 'is_available'];

    protected function casts(): array
    {
        return ['base_price' => 'decimal:2', 'tax_rate_percent' => 'decimal:2', 'discount_value' => 'decimal:2', 'is_available' => 'boolean'];
    }

    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
