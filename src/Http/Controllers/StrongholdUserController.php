<?php

namespace Miguilim\LaravelStronghold\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmPassword;
use Laravel\Fortify\Features;
use Miguilim\LaravelStronghold\Contracts\ProfileViewResponse;
use Miguilim\LaravelStronghold\Models\ConnectedAccount;

class StrongholdUserController extends Controller
{
    /**
     * Show the user profile screen.
     */
    public function show(Request $request): ProfileViewResponse
    {
        $confirmsTwoFactorAuthentication = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');

        $sessions = collect(
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $request->user()->getAuthIdentifier())
                    ->orderBy('last_activity', 'desc')
                    ->get()
        )->map(function ($session) use ($request) {
            $agent = $this->createAgent($session); // TODO

            return (object) [
                'agent' => [
                    'is_desktop' => $agent->isDesktop(),
                    'platform' => $agent->platform(),
                    'browser' => $agent->browser(),
                ],
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === $request->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ];
        });

        // TODO: add connected accounts

        return app(ProfileViewResponse::class, [
            'confirmsTwoFactorAuthentication' => $confirmsTwoFactorAuthentication,
            'sessions' => $sessions,
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

        app(DeletesUsers::class)->delete($request->user()->fresh()); // TODO

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
        if ($user->password) { // Check if user already has a password
            abort(403);
        }

        $request->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        return back(303);
    }
}
