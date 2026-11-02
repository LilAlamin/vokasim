<?php

namespace App\Policies;

use App\Models\PartnerUnit;
use App\Models\User;

class PartnerUnitPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PartnerUnit $unit): bool
    {
        return $user->isAdmin();
    }
}
