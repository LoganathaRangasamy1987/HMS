<?php

namespace App\Models;

use Database\Factories\LabUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabUnit extends Model
{
    /** @use HasFactory<LabUnitFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'code', 'name', 'symbol', 'status'];

    public function parameters(): HasMany
    {
        return $this->hasMany(LabTestParameter::class, 'unit_id');
    }
}
