<?php

namespace Miguilim\LaravelStronghold;

use Closure;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class Stronghold
{
    public const string ACCOUNT_CONNECTED = 'account-connected';

    /**
     * The callback that is responsible for building the profile view response.
     *
     * @var Closure(Request $request, array $data): Response|string|null
     */
    public static Closure|string|null $profileViewResponseCallback = null;

    /**
     * The callback that is responsible for building the confirm new location view response.
     *
     * @var Closure(Request $request, array $data): Response|string|null
     */
    public static Closure|string|null $confirmLocationViewResponseCallback = null;

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
        static::$profileViewResponseCallback = $view;
    }

    /**
     * Specify which view should be used as the confirm new location view.
     *
     * @param  Closure(Request $request, array $data): Response|string  $view
     */
    public static function confirmNewLocationView(Closure|string $view): void
    {
        static::$confirmLocationViewResponseCallback = $view;
    }

    /**
     * Get the profile view response callback.
     */
    public static function profileViewResponse(): Closure|string|null
    {
        return static::$profileViewResponseCallback;
    }

    /**
     * Get the confirm new location view response callback.
     */
    public static function confirmLocationViewResponse(): Closure|string|null
    {
        return static::$confirmLocationViewResponseCallback;
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
