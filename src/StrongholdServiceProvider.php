<?php

namespace Miguilim\LaravelStronghold;

use Miguilim\LaravelStronghold\Actions\NotifySignInDetected;
use Miguilim\LaravelStronghold\Actions\RedirectIfNewLocationConfirmationNeeded;

use Miguilim\LaravelStronghold\Http\Controllers\TwoFactorAuthenticatedSessionController;

use Miguilim\LaravelStronghold\Contracts\CreatesConnectedAccounts;
use Miguilim\LaravelStronghold\Contracts\CreatesUserFromProvider;
use Miguilim\LaravelStronghold\Contracts\DeletesUsers;
use Miguilim\LaravelStronghold\Contracts\SetsUserPasswords;

use Laravel\Fortify\{Features, Fortify};
use Laravel\Fortify\Actions\{CanonicalizeUsername, EnsureLoginIsNotThrottled, PrepareAuthenticatedSession};
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Actions\AttemptToAuthenticate;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Miguilim\LaravelStronghold\Console\Commands\InstallCommand;


class StrongholdServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind action implementations to contracts if they exist
        $this->bindActionImplementations();

        // Bind view response implementations
        $this->app->singleton(
            \Miguilim\LaravelStronghold\Contracts\ProfileViewResponse::class,
            \Miguilim\LaravelStronghold\Http\Responses\ProfileViewResponse::class
        );

        $this->app->singleton(
            \Miguilim\LaravelStronghold\Contracts\ConfirmLocationViewResponse::class,
            \Miguilim\LaravelStronghold\Http\Responses\ConfirmLocationViewResponse::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePublishing();
        $this->configureCommands();
        $this->configureRoutes();

        // Bind extra fortify provider...
        $this->fortifyServiceProviderBoot();
    }

    /**
     * Configure publishing for the package.
     */
    protected function configurePublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/stronghold.php' => config_path('stronghold.php'),
        ], 'stronghold-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'stronghold-migrations');

        $this->publishes([
            __DIR__.'/../stubs/Actions' => base_path('app/Actions/Fortify'),
        ], 'stronghold-stubs');
    }

    /**
     * Configure the commands offered by the application.
     */
    protected function configureCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
        ]);
    }

    /**
     * Configure the routes offered by the application.
     */
    protected function configureRoutes(): void
    {
        if (Fortify::$registersRoutes) {
            Route::group([
                'namespace' => 'Laravel\Fortify\Http\Controllers',
                'domain' => config('fortify.domain', null),
                'prefix' => config('fortify.prefix'),
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/stronghold.php');
            });
        }
    }



    /**
     * Fortify service provider boot.
     */
    public function fortifyServiceProviderBoot(): void
    {
        $this->app->singleton(\Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController::class, TwoFactorAuthenticatedSessionController::class);

        Fortify::authenticateThrough(fn (Request $request) => array_filter([
            config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            Features::enabled(Features::twoFactorAuthentication()) ? RedirectIfTwoFactorAuthenticatable::class : null,
            in_array('confirm-new-location', config('stronghold.features', [])) ? RedirectIfNewLocationConfirmationNeeded::class : null,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
            in_array('sign-in-notification', config('stronghold.features', [])) ? NotifySignInDetected::class : null,
        ]));
    }

    /**
     * Bind action implementations to contracts if they exist.
     */
    protected function bindActionImplementations(): void
    {
        $actions = [
            CreatesConnectedAccounts::class => 'App\\Actions\\Fortify\\CreateConnectedAccount',
            CreatesUserFromProvider::class => 'App\\Actions\\Fortify\\CreateUserFromProvider',
            DeletesUsers::class => 'App\\Actions\\Fortify\\DeleteUser',
            SetsUserPasswords::class => 'App\\Actions\\Fortify\\SetUserPassword',
        ];

        foreach ($actions as $contract => $implementation) {
            if (class_exists($implementation)) {
                $this->app->singleton($contract, $implementation);
            }
        }
    }
}
