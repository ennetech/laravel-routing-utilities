<?php

namespace Ennetech\LaravelRoutingUtilities\Enumerator\Mocks;

class HttpKernelMock extends \App\Http\Kernel
{
    public function __construct()
    {
    }

    /**
     * @return array
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * @return array
     */
    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }


}
