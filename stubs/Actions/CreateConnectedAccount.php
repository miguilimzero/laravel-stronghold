<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\One\User as OAuthOneUser;
use Laravel\Socialite\Two\User as OAuthTwoUser;
use Miguilim\LaravelStronghold\Contracts\CreatesConnectedAccounts;

class CreateConnectedAccount implements CreatesConnectedAccounts
{
    /**
     * Create a connected account for the given user.
     */
    public function create(User $user, string $provider, ProviderUser $providerUser): void
    {
        $token = null;
        $secret = null;
        $refreshToken = null;
        $expiresAt = null;

        if ($providerUser instanceof OAuthTwoUser) {
            $token = $providerUser->token;
            $refreshToken = $providerUser->refreshToken;
            $expiresAt = $providerUser->expiresIn > 0
                ? now()->addSeconds($providerUser->expiresIn)
                : null;
        }

        if ($providerUser instanceof OAuthOneUser) {
            $token = $providerUser->token;
            $secret = $providerUser->tokenSecret;
        }

        $user->connectedAccounts()->create([
            'provider' => $provider,
            'provider_id' => $providerUser->getId(),
            'name' => $providerUser->getName(),
            'nickname' => $providerUser->getNickname(),
            'email' => $providerUser->getEmail(),
            'avatar_path' => $providerUser->getAvatar(),
            'token' => $token,
            'secret' => $secret,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
        ]);
    }
}
