<?php

namespace App\Models;

use Database\Factories\PharmacySupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PharmacySupplier extends Model
{
    /** @use HasFactory<PharmacySupplierFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'code', 'name', 'contact_person', 'phone', 'email', 'tax_registration_number', 'address', 'status'];

    public function purchases(): HasMany
    {
        return $this->hasMany(PharmacyPurchase::class);
    }
}
