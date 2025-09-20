<?php

namespace Miguilim\LaravelStronghold\Http\Responses;

use Miguilim\LaravelStronghold\Contracts\ConfirmNewLocationViewResponse as ConfirmNewLocationViewResponseContract;
use Miguilim\LaravelStronghold\Stronghold;

class ConfirmNewLocationViewResponse implements ConfirmNewLocationViewResponseContract
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
        $callback = Stronghold::confirmNewLocationViewResponse();

        if ($callback instanceof \Closure) {
            return call_user_func($callback, $request, $this->data);
        }

        if (is_string($callback)) {
            return view($callback, $this->data);
        }

        return view('stronghold.confirm-new-location', $this->data);
    }
}