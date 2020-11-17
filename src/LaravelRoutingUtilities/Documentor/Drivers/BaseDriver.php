<?php

namespace Ennetech\LaravelRoutingUtilities\Documentor\Drivers;


use Ennetech\LaravelRoutingUtilities\Enumerator\MiddlewaresEnumerator;
use Ennetech\LaravelRoutingUtilities\Enumerator\RoutesEnumerator;

abstract class BaseDriver
{
    public $config;
    public $enumeratedRoutes;
    public $enumeratedMiddlewares;

    public function __construct($config)
    {
        $this->config = $config;
        $this->enumeratedRoutes = RoutesEnumerator::enumerate($config['basepath']);
        $this->enumeratedMiddlewares = MiddlewaresEnumerator::enumerate();
    }

    abstract function output();

}