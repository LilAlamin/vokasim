<?php

namespace App\Policies;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isMahasiswa() || $user->isDosen() || $user->isAdmin();
    }

    public function view(User $user, Certificate $certificate): Response
    {
        return Certificate::query()->forUser($user)->whereKey($certificate->id)->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isMahasiswa();
    }

    public function update(User $user, Certificate $certificate): bool
    {
        return $user->isMahasiswa() && $certificate->student_id === $user->id
            && in_array($certificate->status, [CertificateStatus::Pending, CertificateStatus::Rejected], true);
    }

    public function delete(User $user, Certificate $certificate): bool
    {
        return $this->update($user, $certificate);
    }

    public function review(User $user, Certificate $certificate): bool
    {
        return ($user->isDosen() || $user->isAdmin()) && $certificate->student_id !== $user->id
            && $certificate->status === CertificateStatus::Pending
            && $this->view($user, $certificate)->allowed();
    }

    public function revoke(User $user, Certificate $certificate): bool
    {
        return ($user->isDosen() || $user->isAdmin()) && $certificate->student_id !== $user->id
            && $certificate->status === CertificateStatus::Verified
            && $this->view($user, $certificate)->allowed();
    }
}
