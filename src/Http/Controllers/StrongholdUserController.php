<?php

namespace Miguilim\LaravelStronghold\Http\Controllers;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Miguilim\LaravelStronghold\Contracts\ProfileViewResponse;
use Miguilim\LaravelStronghold\Contracts\DeletesUsers;
use Miguilim\LaravelStronghold\Contracts\SetsUserPasswords;
use Miguilim\LaravelStronghold\Models\ConnectedAccount;

class StrongholdUserController extends Controller
{
    /**
     * Show the user profile screen.
     */
    public function show(Request $request): mixed
    {
        return app(ProfileViewResponse::class);
    }

    /**
     * Log out from other browser sessions.
     */
    public function destroyOtherBrowserSessions(Request $request, StatefulGuard $guard): RedirectResponse
    {
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
    public function destroyUser(Request $request, StatefulGuard $guard): RedirectResponse
    {
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
        if (! $request->user()->canDisconnectAccount()) {
            abort(403);
        }

        $connectedAccount = ConnectedAccount::query()->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $connectedAccount->delete();

        return back(303);
    }

    /**
     * Set a password for the authenticated user.
     */
    public function passwordStore(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->password) { // Check if user already has a password
            abort(403);
        }

        app(SetsUserPasswords::class)->set($user, $request->all());

        return back(303);
    }
}
