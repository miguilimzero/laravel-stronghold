<?php

namespace Miguilim\LaravelStronghold\Http\Controllers;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Miguilim\LaravelStronghold\Contracts\ProfileViewResponse;
use Miguilim\LaravelStronghold\Models\ConnectedAccount;

class StrongholdUserController extends Controller
{
    /**
     * The guard implementation.
     *
     * @var \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected $guard;

    /**
     * Create a new controller instance.
     *
     * @param  \Illuminate\Contracts\Auth\StatefulGuard  $guard
     * @return void
     */
    public function __construct(StatefulGuard $guard)
    {
        $this->guard = $guard;
    }

    /**
     * Show the user profile screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Miguilim\LaravelStronghold\Contracts\ProfileViewResponse
     */
    public function show(Request $request)
    {
        return app(ProfileViewResponse::class);
    }

    /**
     * Log out from other browser sessions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function destroyOtherBrowserSessions(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => [__('The provided password is incorrect.')],
            ]);
        }

        $this->deleteOtherSessionRecords($request);

        return $request->wantsJson()
            ? response()->json(['message' => 'Other browser sessions have been logged out.'])
            : back()->with('status', 'other-browser-sessions-destroyed');
    }

    /**
     * Delete the other browser session records from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function deleteOtherSessionRecords(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    /**
     * Delete the current user's profile photo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function destroyProfilePhoto(Request $request)
    {
        $request->user()->deleteProfilePhoto();

        return $request->wantsJson()
            ? response()->json(['message' => 'Profile photo deleted.'])
            : back()->with('status', 'profile-photo-deleted');
    }

    /**
     * Delete the current user's account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function destroyUser(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('The provided password is incorrect.')],
            ]);
        }

        $user->delete();

        $this->guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->wantsJson()
            ? response()->json(['message' => 'Account deleted successfully.'])
            : redirect('/')->with('status', 'account-deleted');
    }

    /**
     * Remove the specified connected account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function destroyConnectedAccount(Request $request, $id)
    {
        $connectedAccount = ConnectedAccount::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Ensure user has at least one authentication method
        if (! $request->user()->password && $request->user()->connectedAccounts()->count() === 1) {
            return $request->wantsJson()
                ? response()->json(['message' => 'You must set a password before removing your last connected account.'], 422)
                : back()->withErrors(['account' => 'You must set a password before removing your last connected account.']);
        }

        $connectedAccount->delete();

        return $request->wantsJson()
            ? response()->json(['message' => 'Connected account removed successfully.'])
            : back()->with('status', 'connected-account-removed');
    }

    /**
     * Set a password for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function setPassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        // Check if user already has a password
        if ($user->password) {
            return $request->wantsJson()
                ? response()->json(['message' => 'User already has a password set.'], 422)
                : back()->withErrors(['password' => 'You already have a password set.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        return $request->wantsJson()
            ? response()->json(['message' => 'Password set successfully.'])
            : back()->with('status', 'password-set');
    }
}
