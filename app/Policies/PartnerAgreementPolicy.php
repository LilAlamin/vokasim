<?php

namespace App\Policies;

use App\Models\PartnerAgreement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PartnerAgreementPolicy
{
    public function view(User $user, PartnerAgreement $agreement): Response
    {
        return $user->isAdmin() ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PartnerAgreement $agreement): bool
    {
        return $user->isAdmin();
    }
}
