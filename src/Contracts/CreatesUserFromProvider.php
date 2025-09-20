<?php

namespace Miguilim\LaravelStronghold\Contracts;

use Laravel\Socialite\Contracts\User as ProviderUser;

interface CreatesUserFromProvider
{
    /**
     * Create a new user from a social provider user.
     *
     * @param  string  $provider
     * @param  \Laravel\Socialite\Contracts\User  $providerUser
     * @return \Illuminate\Contracts\Auth\Authenticatable
     */
    public function create(string $provider, ProviderUser $providerUser);
}