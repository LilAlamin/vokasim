<?php

namespace App\Policies;

use App\Models\PortfolioProject;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PortfolioProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isMahasiswa() || $user->isDosen() || $user->isAdmin();
    }

    public function view(User $user, PortfolioProject $portfolioProject): Response
    {
        return PortfolioProject::query()->forUser($user)->whereKey($portfolioProject->id)->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isMahasiswa();
    }

    public function update(User $user, PortfolioProject $portfolioProject): bool
    {
        return $user->isMahasiswa() && $portfolioProject->student_id === $user->id;
    }

    public function delete(User $user, PortfolioProject $portfolioProject): bool
    {
        return $this->update($user, $portfolioProject);
    }
}
