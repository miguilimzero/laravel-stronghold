<?php

namespace Miguilim\LaravelStronghold;

use Closure;

class Stronghold
{
    /**
     * The callback that is responsible for building the profile view response.
     */
    public static callable|string|null $profileViewResponseCallback = null;

    /**
     * The callback that is responsible for building the confirm new location view response.
     */
    public static callable|string|null $confirmNewLocationViewResponseCallback = null;

    /**
     * The callback that is responsible for detecting if new location confirmation is needed.
     */
    public static ?callable $detectNewLocationCallback = null;

    /**
     * Specify which view should be used as the profile view.
     */
    public static function profileView(callable|string $view): void
    {
        static::$profileViewResponseCallback = $view;
    }

    /**
     * Specify which view should be used as the confirm new location view.
     */
    public static function confirmNewLocationView(callable|string $view): void
    {
        static::$confirmNewLocationViewResponseCallback = $view;
    }

    /**
     * Get the profile view response callback.
     */
    public static function profileViewResponse(): callable|string|null
    {
        return static::$profileViewResponseCallback;
    }

    /**
     * Get the confirm new location view response callback.
     */
    public static function confirmNewLocationViewResponse(): callable|string|null
    {
        return static::$confirmNewLocationViewResponseCallback;
    }

    /**
     * Set the callback that determines if new location confirmation is needed.
     */
    public static function detectNewLocationUsing(callable $callback): void
    {
        static::$detectNewLocationCallback = $callback;
    }

    /**
     * Determine if new location confirmation is needed.
     */
    public static function needsNewLocationConfirmation($request, $user): bool
    {
        if (static::$detectNewLocationCallback) {
            return call_user_func(static::$detectNewLocationCallback, $request, $user);
        }

        // Default behavior - always require confirmation
        return true;
    }
}