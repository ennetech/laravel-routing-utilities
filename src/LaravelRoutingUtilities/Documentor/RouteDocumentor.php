<?php

namespace Ennetech\LaravelRoutingUtilities\Documentor;

use Ennetech\LaravelRoutingUtilities\Documentor\Drivers\Swagger2;

class RouteDocumentor
{
    static function execute($driver, $config)
    {
        return (new Swagger2($config))->output();
    }
}
