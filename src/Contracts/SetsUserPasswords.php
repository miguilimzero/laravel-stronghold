<?php

namespace Miguilim\LaravelStronghold\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface SetsUserPasswords
{
    /**
     * Set the user's password.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $input
     * @return void
     */
    public function set(Authenticatable $user, array $input): void;
}