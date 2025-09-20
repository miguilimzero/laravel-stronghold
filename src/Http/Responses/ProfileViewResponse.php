<?php

namespace Miguilim\LaravelStronghold\Http\Responses;

use Miguilim\LaravelStronghold\Contracts\ProfileViewResponse as ProfileViewResponseContract;
use Miguilim\LaravelStronghold\Stronghold;

class ProfileViewResponse implements ProfileViewResponseContract
{
    /**
     * The data to pass to the view.
     */
    protected array $data;

    /**
     * Create a new response instance.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): \Symfony\Component\HttpFoundation\Response
    {
        $callback = Stronghold::profileViewResponse();

        if ($callback instanceof \Closure) {
            return call_user_func($callback, $request, $this->data);
        }

        if (is_string($callback)) {
            return view($callback, $this->data);
        }

        return view('stronghold.profile', $this->data);
    }
}