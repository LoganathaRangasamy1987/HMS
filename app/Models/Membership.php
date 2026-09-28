<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    protected $fillable = ['hospital_id', 'branch_id', 'user_id', 'role_id', 'status'];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('memberships.status', 'active')
            ->whereHas('hospital', fn ($q) => $q->where('status', 'active'))
            ->whereHas('branch', fn ($q) => $q->where('status', 'active'))
            ->whereHas('user', fn ($q) => $q->where('status', 'active'));
    }
}
