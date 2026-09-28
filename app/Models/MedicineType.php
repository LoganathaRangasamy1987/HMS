<?php

namespace App\Models;

use Database\Factories\MedicineTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicineType extends Model
{
    /** @use HasFactory<MedicineTypeFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'name', 'status'];

    public function medicines(): HasMany
    {
        return $this->hasMany(Medicine::class);
    }
}
