<?php

namespace Ennetech\LaravelRoutingUtilities\Enumerator;

use Ennetech\LaravelRoutingUtilities\DocBlockReader;
use Ennetech\LaravelRoutingUtilities\Enumerator\Models\EnumeratedRoute;
use Ennetech\LaravelRoutingUtilities\Enumerator\Mocks\ResourceMock;
use Ennetech\LaravelRoutingUtilities\Enumerator\Models\RouteParameters;
use Ennetech\LaravelRoutingUtilities\HttpContracts\BaseResourceCollection;
use Illuminate\Support\Str;

class RoutesEnumerator
{
    static function enumerate($basePath = "")
    {
        $routeCollection = collect(\Route::getRoutes()->getRoutes());
        return $routeCollection->filter(function ($route) use ($basePath) {
            return $basePath == "" || \Illuminate\Support\Str::startsWith($route->uri, $basePath);
        })->values()->map(function ($route) {
            $enumeratedRoute = new EnumeratedRoute();
            $enumeratedRoute->name = $route->getAction('as');
            $enumeratedRoute->middlewares = $route->getAction('middleware') ?? [];
            $enumeratedRoute->uri = $route->uri;
            $enumeratedRoute->methods = $route->methods;

            $controller = $route->getAction('controller');
            if (isset($controller)) {
                $exploded = explode('@', $controller);

                $controller = [
                    'class' => $exploded[0],
                    'method' => $exploded[1]
                ];

                $rclass = new DocBlockReader($controller['class']);
                $rmethod = new DocBlockReader($controller['class'], $controller['method']);

                $enumeratedRoute->tagDescription = $rclass->getParameter('description');
                $enumeratedRoute->description = $rmethod->getParameter('description');
                $enumeratedRoute->summary = $rmethod->getParameter('summary');
                $enumeratedRoute->deprecated = $rmethod->getParameter('deprecated');

                $controllerClass = \Illuminate\Support\Arr::last(explode('\\', $controller['class']));
                $controllerClass = \Illuminate\Support\Str::before($controllerClass, 'Controller');
                $enumeratedRoute->tag = \Illuminate\Support\Str::kebab($controllerClass);


                $method = new \ReflectionMethod($controller['class'], $controller['method']);
                // Check if we have a FormRequest
                $controller['pars'] = collect($method->getParameters())->map(function ($par) {
                    /* @var $par \ReflectionParameter */
                    return [
                        'name' => $par->getName(),
                        'class' => optional($par->getClass())->getName()
                    ];
                });

                // Categorize parameters
                $enumeratedRoute->parameters = new RouteParameters();

                foreach ($controller['pars'] as $par) {
                    if (isset($par['class'])) {
                        $className = $par['class'];
                        // I cannot instantiate an interface
                        try {
                            $tmp = new $className;
                        } catch (\Throwable $e) {

                        }
                        if ($tmp instanceof \Illuminate\Foundation\Http\FormRequest) {
                            try {
                                $rules = $tmp->rules();
                            } catch (\Exception $e) {
                                $rules = [];
                            }
                            if (method_exists($tmp, 'queryParameters')) {
                                $keys = array_keys($tmp->queryParameters());
                                foreach ($keys as $k) {
                                    if (isset($rules[$k])) {
                                        unset($rules[$k]);
                                    }
                                }
                            }

                            if ($rules == []) {
                                $rules = null;
                            }

                            $enumeratedRoute->requestValidation = $rules;
                            $enumeratedRoute->request = $className;

                            // Extract query parameters
                            if (method_exists($tmp, 'queryParameters')) {
                                $queryParams = $tmp->queryParameters();
                                foreach ($queryParams as $queryParam => $description) {
                                    if (isset($enumeratedRoute->requestValidation[$queryParam])) {
                                        // TODO: extract validation rules
                                        // unset($enumeratedRoute->requestValidation[$queryParam]);
                                    }
                                    $enumeratedRoute->parameters->add('query', $queryParam, false, [], $description);
                                }
                            }
                            $reader = new DocBlockReader(get_class($tmp));
                            $enumeratedRoute->parameters->body = $reader->getParameter("description");
                        } else {

                        }
                    }
                }

                // Check what the controller method returns
                $enumeratedRoute->response = optional($method->getReturnType())->getName();

                $docReturn = $rmethod->getParameter("return");

                if (isset($docReturn)) {
                    $explode = explode("|", $docReturn);

                    foreach ($explode as $exp) {
                        if (Str::startsWith($exp, '\App')) {
                            $enumeratedRoute->response = $exp;
                        }
                    }
                }

                if (isset($enumeratedRoute->response)) {
                    $className = $enumeratedRoute->response;
                    $resourceIstance = new $className(new ResourceMock());

                    if ($resourceIstance instanceof BaseResourceCollection) {
                        $enumeratedRoute->isResponseArray = true;
                    } else {
                        $enumeratedRoute->isResponseArray = false;
                    }

                    $enumeratedRoute->responseValidation = $resourceIstance->rules();
                }

                if (!isset($enumeratedRoute->responseValidation) && $rmethod->getParameter('output')) {
                    $format = $rmethod->getParameter('output');
                    if (!is_array($format)) {
                        $format = explode(";", $format);
                    }

                    $b = [];

                    foreach ($format as $f) {
                        $ee = explode(":", $f);
                        $b[$ee[0]] = $ee[1];
                    }

                    $enumeratedRoute->responseValidation = $b;
                }

                $forceReturnArray = $rmethod->getParameter('returnArray');
                if ($forceReturnArray) {
                    $enumeratedRoute->isResponseArray = true;
                }


                // Path parameters
                $matches = [];
                preg_match_all('|{([a-zA-Z0-9-_]+)}|', $enumeratedRoute->uri, $matches);

                foreach ($matches[1] as $pp) {
                    $rules = $rmethod->getParameter('where');
                    $rules = $rules ? explode("|", $rules) : [];

                    $rp = [];
                    foreach ($rules as $rule) {
                        $e = explode("=", $rule);
                        $rp[$e[0]] = $e[1];
                    }
                    $enumeratedRoute->parameters->add('path', $pp, true, $rp);
                }

            }

            return $enumeratedRoute;
        });
    }
}
