<?php

namespace App\Models;

use Database\Factories\PharmacyManufacturerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PharmacyManufacturer extends Model
{
    /** @use HasFactory<PharmacyManufacturerFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'name', 'status'];

    public function medicines(): HasMany
    {
        return $this->hasMany(Medicine::class);
    }
}
