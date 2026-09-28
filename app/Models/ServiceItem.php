<?php

namespace App\Models;

use Database\Factories\ServiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceItem extends Model
{
    /** @use HasFactory<ServiceItemFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'code', 'name', 'type', 'description', 'currency', 'base_price', 'tax_rate_percent', 'discount_type', 'discount_value', 'status'];

    protected function casts(): array
    {
        return ['base_price' => 'decimal:2', 'tax_rate_percent' => 'decimal:2', 'discount_value' => 'decimal:2'];
    }

    public function branchPrices(): HasMany
    {
        return $this->hasMany(ServiceBranchPrice::class);
    }
}
