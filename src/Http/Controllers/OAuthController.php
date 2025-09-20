<?php

namespace Miguilim\LaravelStronghold\Http\Controllers;

use Exception;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Miguilim\LaravelStronghold\Models\ConnectedAccount;
use Miguilim\LaravelStronghold\Contracts\CreatesUserFromProvider;
use Miguilim\LaravelStronghold\Contracts\CreatesConnectedAccounts;

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
            return $request->wantsJson()
                ? response()->json(['message' => 'Authentication failed.'], 401)
                : redirect()->route('login')->with('error', 'Authentication failed.');
        }

        return DB::transaction(function () use ($request, $guard, $provider, $socialUser) {
            $connectedAccount = ConnectedAccount::firstOrNew([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ]);

            if ($connectedAccount->exists) {
                // Existing connected account - log in the user
                $user = $connectedAccount->user;

                $guard->login($user, true);

                return $request->wantsJson()
                    ? response()->json(['message' => 'Logged in successfully.'])
                    : redirect()->intended(config('fortify.home', '/'));
            }

            // New social account
            if ($guard->check()) {
                // User is authenticated - link account
                app(CreatesConnectedAccounts::class)->create($request->user(), $provider, $socialUser);

                return $request->wantsJson()
                    ? response()->json(['message' => 'Account connected successfully.'])
                    : redirect()->route('profile.show')->with('status', 'account-connected');
            }

            // Not authenticated - check if user exists with this email
            if ($socialUser->getEmail()) {
                $userModel = config('auth.providers.users.model');
                $user = $userModel::where('email', $socialUser->getEmail())->first();

                if ($user) {
                    // User exists with this email - link and log in
                    app(CreatesConnectedAccounts::class)->create($user, $provider, $socialUser);

                    $guard->login($user, true);

                    return $request->wantsJson()
                        ? response()->json(['message' => 'Logged in successfully.'])
                        : redirect()->intended(config('fortify.home', '/'));
                }
            }

            // No existing user - create new user automatically
            // Check if email is provided
            if (!$socialUser->getEmail() || empty(trim($socialUser->getEmail()))) {
                return $request->wantsJson()
                    ? response()->json(['message' => 'Email permission is required. Please allow access to your email address.'], 422)
                    : redirect()->route('login')->with('error', 'Email permission is required. Please allow access to your email address.');
            }

            $user = app(CreatesUserFromProvider::class)->create($provider, $socialUser);

            $guard->login($user, true);

            return $request->wantsJson()
                ? response()->json(['message' => 'Account created and logged in successfully.'])
                : redirect()->intended(config('fortify.home', '/'));
        });
    }

    /**
     * Validate the provider.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateProvider(string $provider): void
    {
        if (! in_array($provider, config('stronghold.providers', []))) {
            throw new \InvalidArgumentException("Invalid provider: {$provider}");
        }
    }
}
