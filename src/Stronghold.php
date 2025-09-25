<?php

namespace Miguilim\LaravelStronghold;

use Closure;

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

            $props = [
                'sessions' => $sessions,
                'connectedAccounts' => request()->user()->connectedAccounts,
                'twoFactorAuthentication' => Features::enabled(Features::twoFactorAuthentication()),
                'confirmsTwoFactorAuthentication' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
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
}
