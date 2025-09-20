<?php

namespace Miguilim\LaravelStronghold\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmPassword;
use Laravel\Fortify\Features;
use Miguilim\LaravelStronghold\Contracts\ProfileViewResponse;
use Miguilim\LaravelStronghold\Contracts\DeletesUsers;
use Miguilim\LaravelStronghold\Contracts\SetsUserPasswords;
use Miguilim\LaravelStronghold\Models\ConnectedAccount;

class StrongholdUserController extends Controller
{
    /**
     * Show the user profile screen.
     */
    public function show(Request $request): ProfileViewResponse
    {
        $confirmsTwoFactorAuthentication = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');

        if (config('session.driver') !== 'database') {
            throw new \RuntimeException('Session driver must be set to "database" to view browser sessions.');
        }

        $sessions = collect(
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->orderBy('last_activity', 'desc')
                ->get()
        )->map(function ($session) use ($request) {
            $agent = new \WhichBrowser\Parser($session->user_agent);

            return (object) [
                'agent' => [
                    'is_desktop' => $agent->isType('desktop'),
                    'platform' => $agent->os->toString(),
                    'browser' => $agent->browser->toString(),
                ],
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === $request->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ];
        });

        $connectedAccounts = $request->user()->connectedAccounts ?? collect();

        return app(ProfileViewResponse::class, [
            'confirmsTwoFactorAuthentication' => $confirmsTwoFactorAuthentication,
            'sessions' => $sessions,
            'connectedAccounts' => $connectedAccounts,
        ]);
    }

    /**
     * Log out from other browser sessions.
     */
    public function destroyOtherBrowserSessions(Request $request, StatefulGuard $guard): RedirectResponse
    {
        $confirmed = app(ConfirmPassword::class)(
            $guard, $request->user(), $request->password
        );

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'password' => __('The password is incorrect.'),
            ]);
        }

        if (config('session.driver') !== 'database') {
            throw new \RuntimeException('Session driver must be set to "database" to logout other browser sessions.');
        }

        $guard->logoutOtherDevices($request->password);

        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back(303);
    }

    /**
     * Delete the current user's profile photo.
     */
    public function destroyProfilePhoto(Request $request): RedirectResponse
    {
        $request->user()->deleteProfilePhoto();

        return back(303);
    }

    /**
     * Delete the current user's account.
     */
    public function destroy(Request $request, StatefulGuard $guard): RedirectResponse
    {
        $confirmed = app(ConfirmPassword::class)(
            $guard, $request->user(), $request->password
        );

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'password' => __('The password is incorrect.'),
            ]);
        }

        app(DeletesUsers::class)->delete($request->user()->fresh());

        $guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Remove the specified connected account.
     */
    public function destroyConnectedAccount(Request $request, string $id): RedirectResponse
    {
        $connectedAccount = ConnectedAccount::query()->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (! $request->user()->canDisconnectAccount()) {
            abort(403);
        }

        $connectedAccount->delete();

        return back(303);
    }

    /**
     * Set a password for the authenticated user.
     */
    public function setPassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->password) { // Check if user already has a password
            abort(403);
        }

        app(SetsUserPasswords::class)->set($user, $request->all());

        return back(303);
    }
}
