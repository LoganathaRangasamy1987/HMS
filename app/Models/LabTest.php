<?php

namespace App\Models;

use Database\Factories\LabTestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabTest extends Model
{
    /** @use HasFactory<LabTestFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'code', 'name', 'status', 'active_version_id'];

    public function versions(): HasMany
    {
        return $this->hasMany(LabTestVersion::class);
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(LabTestVersion::class, 'active_version_id');
    }
}
