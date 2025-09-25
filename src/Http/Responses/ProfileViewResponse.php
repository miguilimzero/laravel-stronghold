<?php

namespace Miguilim\LaravelStronghold\Http\Responses;

use Miguilim\LaravelStronghold\Contracts\ProfileViewResponse as ProfileViewResponseContract;
use Miguilim\LaravelStronghold\Stronghold;
use Illuminate\Contracts\Support\Responsable;

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
    public function toResponse($request): mixed
    {
        $callback = Stronghold::profileViewResponse();

        if (! is_callable($callback) || is_string($callback)) {
            return view($callback, $this->data);
        }

        $response = call_user_func($callback, $request, $this->data);

        if ($response instanceof Responsable) {
            return $response->toResponse($request);
        }

        return $response;
    }
}
