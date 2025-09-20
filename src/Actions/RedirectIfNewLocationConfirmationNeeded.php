<?php

namespace Miguilim\LaravelStronghold\Actions;

use Illuminate\Support\Str;

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

        $userLastIp = $user->last_ip_address ?? $user->ip_address;
        $currentIp = $request->ip();

        if (! $user->email_verified_at) { // Do not challenge if user is not verified - avoids unexpected redirects
            return $next($request);
        }

        if (config('stronghold.new_location_confirmation.check_ip_only')) {
            if ($userLastIp && $userLastIp === $currentIp) {
                return $next($request);
            }
        } else {
            // TODO
        }

        return $this->newDeviceConfirmationResponse($request, $user);
    }

     /**
     * Get the new device detected response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function newDeviceConfirmationResponse($request, $user)
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
