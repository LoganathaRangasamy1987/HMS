<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['hospital_id', 'name', 'code', 'email', 'phone', 'address', 'city', 'timezone', 'pharmacy_expiry_warning_days', 'status'];

    protected function casts(): array
    {
        return ['pharmacy_expiry_warning_days' => 'integer'];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function registeredPatients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
