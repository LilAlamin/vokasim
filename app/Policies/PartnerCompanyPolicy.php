<?php

namespace App\Policies;

use App\Models\PartnerCompany;
use App\Models\User;

class PartnerCompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, PartnerCompany $company): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PartnerCompany $company): bool
    {
        return $user->isAdmin();
    }
}
