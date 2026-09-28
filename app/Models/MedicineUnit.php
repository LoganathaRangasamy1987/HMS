<?php

namespace App\Models;

use Database\Factories\MedicineUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicineUnit extends Model
{
    /** @use HasFactory<MedicineUnitFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'name', 'symbol', 'status'];

    public function purchasedMedicines(): HasMany
    {
        return $this->hasMany(Medicine::class, 'purchase_unit_id');
    }

    public function soldMedicines(): HasMany
    {
        return $this->hasMany(Medicine::class, 'sale_unit_id');
    }
}
