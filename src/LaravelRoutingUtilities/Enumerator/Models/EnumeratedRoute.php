<?php

namespace Ennetech\LaravelRoutingUtilities\Enumerator\Models;


class EnumeratedRoute
{
    public $uri;
    public $methods;
    public $name;
    public $summary;
    public $deprecated;
    public $description;
    public $middlewares;
    public $tag;
    public $tagDescription;

    public $request;
    public $response;

    public $isResponseArray;

    /** @var RouteParameters */
    public $parameters;

    public $requestValidation;
    public $responseValidation;
}
