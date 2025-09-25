<?php

use Miguilim\LaravelStronghold\Http\Controllers\NewLocationAuthenticatedSessionController;
use Miguilim\LaravelStronghold\Http\Controllers\StrongholdUserController;
use Miguilim\LaravelStronghold\Http\Controllers\OAuthController;

use Illuminate\Support\Facades\Route;

use Laravel\Fortify\RoutePath;

Route::group(['middleware' => config('fortify.middleware', ['web'])], function () {
    $enableViews = config('fortify.views', true);

    $limiter = config('fortify.limiters.login');
    $limiterTwoFactor = config('fortify.limiters.two-factor');

    $guestMiddleware = 'guest:' . config('fortify.guard');
    $authMiddleware = config('fortify.auth_middleware', 'auth') . ':' . config('fortify.guard');

    if ($enableViews) {
        Route::get(RoutePath::for('profile.show', '/user/profile'), [StrongholdUserController::class, 'show'])
            ->middleware([$authMiddleware])
            ->name('profile.show');
    }

    Route::delete(RoutePath::for('current-user-photo.destroy', '/user/profile-photo'), [StrongholdUserController::class, 'destroyProfilePhoto'])
        ->middleware([$authMiddleware])
        ->name('current-user-photo.destroy');

    Route::delete(RoutePath::for('other-browser-sessions.destroy', '/user/other-browser-sessions'), [StrongholdUserController::class, 'destroyOtherBrowserSessions'])
        ->middleware([$authMiddleware, 'password.confirm'])
        ->name('other-browser-sessions.destroy'); // Keeping the same name as Jetstream

    Route::delete(RoutePath::for('current-user.destroy', '/user'), [StrongholdUserController::class, 'destroyUser'])
        ->middleware([$authMiddleware, 'password.confirm'])
        ->name('current-user.destroy');

    if (in_array('confirm-new-location', config('stronghold.features', []))) {
        if ($enableViews) {
            Route::get(RoutePath::for('confirm-location.login', '/confirm-location'), [NewLocationAuthenticatedSessionController::class, 'create'])
                ->middleware([$guestMiddleware])
                ->name('confirm-location.login');
        }

        Route::post(RoutePath::for('confirm-location.login', '/confirm-location'), [NewLocationAuthenticatedSessionController::class, 'store'])
            ->middleware(array_filter([
                $guestMiddleware,
                $limiter ? "throttle:{$limiter}" : null,
            ]))
            ->name('confirm-location.login.store');
    }

    if (in_array('socialite', config('stronghold.features', []))) {
        Route::get(RoutePath::for('oauth.redirect', '/oauth/{provider}'), [OAuthController::class, 'redirectToProvider'])
            ->middleware([$guestMiddleware])
            ->name('oauth.redirect');

            Route::get(RoutePath::for('oauth.callback', '/oauth/{provider}/callback'), [OAuthController::class, 'handleProviderCallback'])
                ->middleware(array_filter([
                    $guestMiddleware,
                    $limiterTwoFactor ? "throttle:{$limiterTwoFactor}" : null,
                ]))
                ->name('oauth.callback');

        Route::delete(RoutePath::for('connected-accounts.destroy', '/user/connected-account/{id}'), [StrongholdUserController::class, 'destroyConnectedAccount'])
            ->middleware([$authMiddleware])
            ->name('connected-accounts.destroy');

        Route::put(RoutePath::for('user-password.set', '/user/set-password'), [StrongholdUserController::class, 'passwordSet'])
            ->middleware([$authMiddleware])
            ->name('user-password.set');
    }
});
