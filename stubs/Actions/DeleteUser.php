<?php

namespace App\Actions\Fortify;

use App\Models\User;

use Miguilim\LaravelStronghold\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        $user->deleteProfilePhoto();
        $user->connectedAccounts->each->delete();
        $user->delete();
    }
}
