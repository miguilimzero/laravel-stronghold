<?php

namespace Miguilim\LaravelStronghold\Http\Controllers;

use Exception;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;
use Miguilim\LaravelStronghold\Models\ConnectedAccount;
use Miguilim\LaravelStronghold\Contracts\CreatesUserFromProvider;
use Miguilim\LaravelStronghold\Contracts\CreatesConnectedAccounts;
use Miguilim\LaravelStronghold\Stronghold;

class OAuthController extends Controller
{
    /**
     * Redirect the user to the provider authentication page.
     */
    public function redirectToProvider(Request $request, string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the callback from the provider.
     */
    public function handleProviderCallback(Request $request, StatefulGuard $guard, string $provider): JsonResponse|RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Exception $e) {
            return redirect()->route('login')->with('error', __('Social login authentication failed. Please try again or contact support if the problem persists.'));
        }

        // New social account (User is logged in and is trying to connect an account)
        if ($guard->check()) {
            $user = $guard->user();

            if ($this->hasSocialAccount($user->id, $provider)) {
                return redirect()->route('profile.show')->with('error', __('You already have a connected account for this provider.'));
            }

            app(CreatesConnectedAccounts::class)->create($user, $provider, $socialUser);

            return redirect()->route('profile.show')->with('status', Stronghold::ACCOUNT_CONNECTED);
        }

        // User is trying to login (or create a new account)
        $connectedAccount = ConnectedAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        // Existing connected account - log in the user
        if ($connectedAccount) {
            $user = $connectedAccount->user;

            $guard->login($user, config('stronghold.socialite_remember', true));

            return redirect()->intended(config('fortify.home', '/'));
        }

        // Not-existing connected account - first check if we have the email address
        if (! $socialUser->getEmail() || empty(trim($socialUser->getEmail()))) {
            return redirect()->route('login')->with('error', __('Email permission is required. Please allow access to your email address information.'));
        }

        // User exists but there is no connected account for such provider
        $userModel = config('auth.providers.users.model');
        if ($userModel::where('email', $socialUser->getEmail())->first()) {
            return redirect()->route('login')->with('error', __('An account with this email address already exists. Please log in with your existing credentials and then connect your social account from your profile page.'));
        }

        // User do not exists - create the account from the provider
        $user = app(CreatesUserFromProvider::class)->create($provider, $socialUser);

        $guard->login($user, config('stronghold.socialite_remember', true));

        return redirect()->intended(config('fortify.home', '/'));
    }

    /**
     * Validate the provider.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateProvider(string $provider): void
    {
        if (! in_array($provider, config('stronghold.socialite_providers', []))) {
            if (config('app.debug')) {
                throw new \InvalidArgumentException("Invalid Stronghold socialite provider: {$provider}");
            }

            abort(404);
        }
    }

    protected function hasSocialAccount(mixed $userId, string $provider): bool
    {
        return ConnectedAccount::query()->where('user_id', $userId)->where('provider', $provider)->first() !== null;
    }
}
