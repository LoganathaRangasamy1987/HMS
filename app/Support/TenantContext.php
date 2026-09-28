<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Membership;

class TenantContext
{
    private ?Membership $current = null;

    public function set(Membership $membership): void
    {
        $this->current = $membership;
    }

    public function membership(): Membership
    {
        abort_unless($this->current, 403, 'Select an authorized branch.');

        return $this->current;
    }

    public function hospital(): Hospital
    {
        return $this->membership()->hospital;
    }

    public function branch(): Branch
    {
        return $this->membership()->branch;
    }

    public function hospitalId(): int
    {
        return $this->membership()->hospital_id;
    }

    public function branchId(): int
    {
        return $this->membership()->branch_id;
    }

    public function isAdmin(): bool
    {
        return $this->current && Membership::query()->active()->whereKey($this->current->id)
            ->where('user_id', auth()->id())->whereHas('role', fn ($q) => $q->where('name', 'HOSPITAL_ADMIN'))->exists();
    }

    public function can(string $permission): bool
    {
        return $this->current && Membership::query()->active()->whereKey($this->current->id)
            ->where('user_id', auth()->id())->whereHas('role.permissions', fn ($q) => $q->where('name', $permission))->exists();
    }
}
