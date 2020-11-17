<?php

namespace Ennetech\LaravelRoutingUtilities\Enumerator;


use Ennetech\LaravelRoutingUtilities\DocBlockReader;
use Ennetech\LaravelRoutingUtilities\Enumerator\Mocks\HttpKernelMock;

class MiddlewaresEnumerator
{
    static function enumerate()
    {
        $httpKernel = new HttpKernelMock();

        $middlewareGroups = $httpKernel->getMiddlewareGroups();
        $routeMiddleware = $httpKernel->getRouteMiddleware();

        $classes = [];

        foreach ($middlewareGroups as $key => $values) {
            $classesTmp = array_map(function ($short) use ($routeMiddleware){
                if (strpos($short, '\\') !== false) {
                    return $short;
                }
                $name = \Illuminate\Support\Arr::first(explode(':', $short));
                if (isset($routeMiddleware[$name])) {
                    return $routeMiddleware[$name];
                }
            }, $values);

            $classes[$key] = [
                'type' => 'group',
                'name' => $key,
                'classes' => $classesTmp
            ];
        }

        foreach ($routeMiddleware as $key => $class) {
            $classes[$key] = [
                'type' => 'single',
                'name' => $key,
                'classes' => [$class]
            ];
        }

        foreach ($classes as &$class) {

            $class['classes'] = collect($class['classes'])
                ->map(function ($className) {
                    $addon = null;

                    $reflectedHandle = new DocBlockReader($className, 'handle');

                    $name = $reflectedHandle->getParameter('security');
                    if (isset($name)) {
                        $addon = [
                            'security' => $name,
                            'securityDescription' => $reflectedHandle->getParameter('securityDescription'),
                            'securityParameterName' => $reflectedHandle->getParameter('securityParameterName'),
                            'securityParameterIn' => $reflectedHandle->getParameter('securityParameterIn'),
                            'securityType' => $reflectedHandle->getParameter('securityType'),
                        ];
                    }

                    return [
                        'name' => $className,
                        'addon' => $addon
                    ];
                });
        }

        return $classes;
    }
}
