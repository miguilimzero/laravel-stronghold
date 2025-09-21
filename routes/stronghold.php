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

    if ($enableViews) {
        Route::get(RoutePath::for('profile.show', '/user/profile'), [StrongholdUserController::class, 'show'])
            ->name('profile.show');
    }

    Route::delete(RoutePath::for('other-browser-sessions.destroy', '/user/other-browser-sessions'), [StrongholdUserController::class, 'destroyOtherBrowserSessions'])
        ->name('other-browser-sessions.destroy');

    Route::delete(RoutePath::for('current-user-photo.destroy', '/user/profile-photo'), [StrongholdUserController::class, 'destroyProfilePhoto'])
        ->name('current-user-photo.destroy');

    Route::delete(RoutePath::for('current-user.destroy', '/user'), [StrongholdUserController::class, 'destroyUser'])
        ->name('current-user.destroy');

    if (in_array('confirm-new-location', config('stronghold.features', []))) {
        if ($enableViews) {
            Route::get(RoutePath::for('confirm-new-location.login', '/confirm-new-location'), [NewLocationAuthenticatedSessionController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('confirm-new-location.login');
        }

        Route::post(RoutePath::for('confirm-new-location.login', '/confirm-new-location'), [NewLocationAuthenticatedSessionController::class, 'store'])
            ->middleware(array_filter([
                'guest:' . config('fortify.guard'),
                $limiter ? "throttle:{$limiter}" : null,
            ]));
    }

    if (in_array('socialite', config('stronghold.features', []))) {
        Route::get(RoutePath::for('oauth.redirect', '/oauth/{provider}'), [OAuthController::class, 'redirectToProvider'])
            ->middleware(['guest:'.config('fortify.guard')])
            ->name('oauth.redirect');

            Route::get(RoutePath::for('oauth.callback', '/oauth/{provider}/callback'), [OAuthController::class, 'handleProviderCallback'])
                ->middleware(array_filter([
                    'guest:' . config('fortify.guard'),
                    $limiterTwoFactor ? "throttle:{$limiterTwoFactor}" : null,
                ]))
                ->name('oauth.callback');

        Route::delete(RoutePath::for('connected-accounts.destroy', '/user/connected-account/{id}'), [StrongholdUserController::class, 'destroyConnectedAccount'])
            ->middleware(['auth'])
            ->name('connected-accounts.destroy');

        Route::put(RoutePath::for('user-password.set', '/user/set-password'), [StrongholdUserController::class, 'setPassword'])
            ->middleware(['auth'])
            ->name('user-password.set');
    }
});
