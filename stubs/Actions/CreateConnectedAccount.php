<?php

namespace App\Actions\Fortify;

use App\Models\User;

use Miguilim\LaravelStronghold\Contracts\CreatesConnectedAccounts;

use Laravel\Socialite\Contracts\User as ProviderUser;

class CreateConnectedAccount implements CreatesConnectedAccounts
{
    /**
     * Create a connected account for the given user.
     */
    public function create(User $user, string $provider, ProviderUser $providerUser): void
    {
        $user->connectedAccounts()->create([
            'provider' => $provider,
            'provider_id' => $providerUser->getId(),
            'name' => $providerUser->getName(),
            'nickname' => $providerUser->getNickname(),
            'email' => $providerUser->getEmail(),
            'avatar_path' => $providerUser->getAvatar(),
            'token' => $providerUser->token,
            'refresh_token' => $providerUser->refreshToken ?? null,
            'expires_at' => $providerUser->expiresIn ? now()->addSeconds($providerUser->expiresIn) : null,
        ]);
    }
}
