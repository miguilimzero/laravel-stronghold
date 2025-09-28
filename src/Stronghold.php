<?php

namespace Miguilim\LaravelStronghold;

use Closure;

use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\SimpleViewResponse;
use Miguilim\LaravelStronghold\Contracts\ConfirmLocationViewResponse;
use Miguilim\LaravelStronghold\Contracts\ProfileViewResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use Carbon\Carbon;

class Stronghold
{
    public const string OTHER_BROWSER_SESSIONS_DESTROYED = 'other-browser-sessions-destroyed';
    public const string PROFILE_PHOTO_DESTROYED = 'profile-photo-destroyed';
    public const string SOCIALITE_ACCOUNT_CONNECTED = 'socialite-account-connected';
    public const string SOCIALITE_ACCOUNT_DESTROYED = 'socialite-account-destroyed';
    public const string PASSWORD_SET = 'password-set';

    /**
     * The callback that is responsible for detecting if new location confirmation is needed.
     *
     * @var Closure(Request $request, Authenticatable $user): bool|null
     */
    public static ?Closure $detectNewLocationCallback = null;

    /**
     * Specify which view should be used as the profile view.
     *
     * @param  Closure(Request $request, array $data): Response|string  $view
     */
    public static function profileView(Closure|string $view): void
    {
        app()->singleton(ProfileViewResponse::class, function () use ($view) {
            if (config('session.driver') !== 'database') {
                throw new \RuntimeException('Session driver must be set to "database" to view browser sessions.');
            }

            $sessions = collect(
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', request()->user()->getAuthIdentifier())
                    ->orderBy('last_activity', 'desc')
                    ->get()
            )->map(function ($session) {
                $agent = new \WhichBrowser\Parser($session->user_agent);

                return (object) [
                    'agent' => [
                        'is_desktop' => $agent->isType('desktop'),
                        'platform' => $agent->os->toString(),
                        'browser' => $agent->browser->toString(),
                    ],
                    'ip_address' => $session->ip_address,
                    'is_current_device' => $session->id === request()->session()->getId(),
                    'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                ];
            });

            $hasSocialite = in_array('socialite', config('stronghold.features', []));
            $hasTwoFactor = Features::enabled(Features::twoFactorAuthentication());

            $props = [
                'sessions' => $sessions,
                'socialite' => $hasSocialite,
                'socialiteProviders' => $hasSocialite ? config('stronghold.socialite_providers', []) : [],
                'connectedAccounts' => $hasSocialite ? request()->user()->connectedAccounts : [],
                'twoFactorAuthentication' => $hasTwoFactor,
                'confirmsTwoFactorAuthentication' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
                'userTwoFactorEnabled' => $hasTwoFactor ? request()->user()->hasEnabledTwoFactorAuthentication() : false,
                'userHasPassword' => request()->user()->password !== null,
            ];

            if (! is_callable($view) || is_string($view)) {
                return view($view, $props);
            }

            $response = call_user_func($view, $props);

            if ($response instanceof Responsable) {
                return $response->toResponse($view);
            }

            return $response;
        });
    }

    /**
     * Specify which view should be used as the confirm new location view.
     *
     * @param  Closure(Request $request, array $data): Response|string  $view
     */
    public static function confirmLocationView(Closure|string $view): void
    {
        app()->singleton(ConfirmLocationViewResponse::class, function () use ($view) {
            return new SimpleViewResponse($view);
        });
    }
    /**
     * Set the callback that determines if new location confirmation is needed.
     *
     * @param  Closure(Request $request, Authenticatable $user): bool  $callback
     */
    public static function detectNewLocationUsing(Closure $callback): void
    {
        static::$detectNewLocationCallback = $callback;
    }

    /**
     * Determine if new location confirmation is needed.
     */
    public static function needsNewLocationConfirmation(Request $request, Authenticatable $user): bool
    {
        if (static::$detectNewLocationCallback) {
            return call_user_func(static::$detectNewLocationCallback, $request, $user);
        }

        // Default behavior - always require confirmation
        return true;
    }

    /**
     * Get session status message converted into a human-readable sentence.
     */
    public static function getSessionStatusMessage(): ?string
    {
        $message = request()->session()->get('status');

        return match ($message) {
            Fortify::PASSWORD_UPDATED                    => __('Your password has been updated.'),
            Fortify::PROFILE_INFORMATION_UPDATED         => __('Your profile information has been updated.'),
            Fortify::RECOVERY_CODES_GENERATED            => __('New recovery codes have been generated.'),
            Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED => __('Two factor authentication has been confirmed.'),
            Fortify::TWO_FACTOR_AUTHENTICATION_DISABLED  => __('Two factor authentication has been disabled.'),
            Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED   => __('Two factor authentication has been enabled.'),
            Fortify::VERIFICATION_LINK_SENT              => __('A verification link has been sent to your email address.'),
            static::OTHER_BROWSER_SESSIONS_DESTROYED     => __('Other browser sessions have been logged out from your account.'),
            static::PROFILE_PHOTO_DESTROYED              => __('Your profile photo has been removed.'),
            static::SOCIALITE_ACCOUNT_CONNECTED          => __('A social account has been connected to your account.'),
            static::SOCIALITE_ACCOUNT_DESTROYED          => __('A social account has been removed from your account.'),
            static::PASSWORD_SET                         => __('Your password has been set.'),
            default                                      => $message,
        };
    }
}
