<?php

namespace App\Models;

use Database\Factories\LabSampleTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabSampleType extends Model
{
    /** @use HasFactory<LabSampleTypeFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'code', 'name', 'status'];

    public function versions(): HasMany
    {
        return $this->hasMany(LabTestVersion::class, 'sample_type_id');
    }
}
