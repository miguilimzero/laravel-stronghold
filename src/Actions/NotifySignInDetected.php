<?php

namespace Miguilim\LaravelStronghold\Actions;

class NotifySignInDetected
{
    public function handle($request, $next)
    {
        $request->user()->notify(new \Miguilim\LaravelStronghold\Notifications\SignInDetected($request));

        return $next($request);
    }
}
