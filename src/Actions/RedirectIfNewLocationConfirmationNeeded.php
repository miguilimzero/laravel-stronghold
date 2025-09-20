<?php

namespace Miguilim\LaravelStronghold\Actions;

use App\Models\User;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Miguilim\LaravelStronghold\Stronghold;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNewLocationConfirmationNeeded extends RedirectIfTwoFactorAuthenticatable
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        $user = $this->validateCredentials($request);

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) { // Do not challenge if user is not verified - avoids unexpected redirects
            return $next($request);
        }

        if (!Stronghold::needsNewLocationConfirmation($request, $user)) {
            return $next($request);
        }

        return $this->newDeviceConfirmationResponse($request, $user);
    }

    /**
     * Get the new device detected response.
     */
    protected function newDeviceConfirmationResponse(Request $request, User $user): Response
    {
        $confirmationCode = strtoupper(Str::random(config('stronghold.new_location_confirmation.code_length')));

        $remember = $request->boolean('remember');

        $request->session()->put([
            'login.confirmation.id' => $user->getKey(),
            'login.confirmation.remember' => $remember,
            'login.confirmation.code' => $confirmationCode,
            'login.confirmation.expires_at' => now()->addMinutes(config('stronghold.new_location_confirmation.code_expiration')),
        ]);

        $user->notify(new \Miguilim\LaravelStronghold\Notifications\NewLocationConfirmation($request, $confirmationCode));

        return $request->wantsJson()
                    ? response()->json(['confirm_new_location' => true])
                    : redirect()->route('confirm-new-location.login');
    }
}
