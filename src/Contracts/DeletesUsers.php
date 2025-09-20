<?php

namespace Miguilim\LaravelStronghold\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface DeletesUsers
{
    /**
     * Delete the given user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return void
     */
    public function delete(Authenticatable $user): void;
}