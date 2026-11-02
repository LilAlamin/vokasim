<?php

namespace App\Policies;

use App\Models\PracticumMaterial;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PracticumMaterialPolicy
{
    public function view(User $user, PracticumMaterial $material): bool
    {
        return Gate::forUser($user)->allows('view', $material->module);
    }

    public function delete(User $user, PracticumMaterial $material): bool
    {
        return Gate::forUser($user)->allows('manage', $material->module);
    }
}
