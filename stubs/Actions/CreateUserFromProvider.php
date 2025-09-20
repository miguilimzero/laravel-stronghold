<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;

use Miguilim\LaravelStronghold\Contracts\CreatesConnectedAccounts;
use Miguilim\LaravelStronghold\Contracts\CreatesUserFromProvider;

use Laravel\Socialite\Contracts\User as ProviderUser;

class CreateUserFromProvider implements CreatesUserFromProvider
{
    /**
     * Create a new action instance.
     */
    public function __construct(public CreatesConnectedAccounts $createsConnectedAccounts)
    {
    }

    /**
     * Create a new user from a social provider user.
     *
     * @param  \Laravel\Socialite\Contracts\User  $providerUser
     */
    public function create(string $provider, ProviderUser $providerUser): User
    {
        return DB::transaction(fn() => tap(User::query()->create([
            'name' => $providerUser->getName() ?? $providerUser->getNickname(),
            'email' => $providerUser->getEmail(),
        ]), function (User $user) use ($provider, $providerUser) {
            $user->markEmailAsVerified();

            if ($providerUser->getAvatar()) {
                $user->setProfilePhotoFromUrl($providerUser->getAvatar());
            }

            $this->createsConnectedAccounts->create($user, $provider, $providerUser);
        }));
    }
}
