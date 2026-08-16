<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Shared authorization policy for every user-owned financial entity. */
class OwnerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return (int) $model->getAttribute('user_id') === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->exists;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }
}
