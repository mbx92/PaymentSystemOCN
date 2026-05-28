<?php

namespace App\Policies;

use App\ERP\Core\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasRole('admin') && ! $company->journalEntries()->exists();
    }
}
