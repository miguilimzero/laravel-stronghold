<?php

namespace App\Actions\Fortify;

use App\Models\User;

use App\Actions\Fortify\PasswordValidationRules;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Miguilim\LaravelStronghold\Contracts\SetsUserPasswords;

class SetUserPassword implements SetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param array<string, mixed> $input
     */
    public function set(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validateWithBag('setPassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
