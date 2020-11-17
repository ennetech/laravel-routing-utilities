<?php

namespace Ennetech\LaravelRoutingUtilities\Enumerator\Mocks;


class ResourceMock
{
    public function __get($key)
    {
        return $key;
    }

    /**
     * Dynamically pass method calls to the underlying resource.
     *
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $method . is_array($parameters) ? 'ARRAY' : $parameters;
    }
}
