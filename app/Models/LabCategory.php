<?php

namespace App\Models;

use Database\Factories\LabCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabCategory extends Model
{
    /** @use HasFactory<LabCategoryFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'code', 'name', 'status'];

    public function versions(): HasMany
    {
        return $this->hasMany(LabTestVersion::class, 'category_id');
    }
}
