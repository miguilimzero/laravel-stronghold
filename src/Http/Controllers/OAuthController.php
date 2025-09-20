<?php

namespace Miguilim\LaravelStronghold\Http\Controllers;

use Exception;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Miguilim\LaravelStronghold\Models\ConnectedAccount;

class OAuthController extends Controller
{
    /**
     * The guard implementation.
     *
     * @var \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected $guard;

    /**
     * Create a new controller instance.
     *
     * @param  \Illuminate\Contracts\Auth\StatefulGuard  $guard
     * @return void
     */
    public function __construct(StatefulGuard $guard)
    {
        $this->guard = $guard;
    }

    /**
     * Redirect the user to the provider authentication page.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToProvider(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the callback from the provider.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $provider
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function handleProviderCallback(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Exception $e) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Authentication failed.'], 401)
                : redirect()->route('login')->with('error', 'Authentication failed.');
        }

        return DB::transaction(function () use ($request, $provider, $socialUser) {
            $connectedAccount = ConnectedAccount::firstOrNew([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ]);

            if ($connectedAccount->exists) {
                // Existing connected account - log in the user
                $user = $connectedAccount->user;
                
                $this->guard->login($user, true);
                
                return $request->wantsJson()
                    ? response()->json(['message' => 'Logged in successfully.'])
                    : redirect()->intended(config('fortify.home', '/'));
            }

            // New social account
            if ($this->guard->check()) {
                // User is authenticated - link account
                $connectedAccount->user_id = $request->user()->id;
                $connectedAccount->fill([
                    'name' => $socialUser->getName(),
                    'nickname' => $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    'avatar_path' => $socialUser->getAvatar(),
                    'token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken ?? null,
                    'expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                ]);
                $connectedAccount->save();

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
                    $connectedAccount->user_id = $user->id;
                    $connectedAccount->fill([
                        'name' => $socialUser->getName(),
                        'nickname' => $socialUser->getNickname(),
                        'email' => $socialUser->getEmail(),
                        'avatar_path' => $socialUser->getAvatar(),
                        'token' => $socialUser->token,
                        'refresh_token' => $socialUser->refreshToken ?? null,
                        'expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    ]);
                    $connectedAccount->save();

                    $this->guard->login($user, true);

                    return $request->wantsJson()
                        ? response()->json(['message' => 'Logged in successfully.'])
                        : redirect()->intended(config('fortify.home', '/'));
                }
            }

            // No existing user - redirect to registration
            session()->put('socialite_provider', [
                'provider' => $provider,
                'user' => $socialUser,
            ]);

            return $request->wantsJson()
                ? response()->json(['message' => 'Please complete registration.', 'redirect' => route('register')], 422)
                : redirect()->route('register')->with('status', 'complete-registration');
        });
    }

    /**
     * Validate the provider.
     *
     * @param  string  $provider
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function validateProvider(string $provider)
    {
        if (! in_array($provider, config('stronghold.providers', []))) {
            throw new \InvalidArgumentException("Invalid provider: {$provider}");
        }
    }
}