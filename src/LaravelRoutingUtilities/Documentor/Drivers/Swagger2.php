<?php

namespace Ennetech\LaravelRoutingUtilities\Documentor\Drivers;


use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Swagger2 extends BaseDriver
{
    private $paths = [];
    private $definitions = [];
    private $parameters = [];
    private $securityDefinitions = [];
    private $tags = [];

    private function addDefinition($name, $properties = [], $required = [])
    {
        $key = Arr::last(explode('\\', $name));

        // TODO: decide how to discriminate
        $key = Str::random(12) . '/' . $key;

        $definition = [
            'title' => $key,
            'required' => $required,
            'properties' => $properties,
        ];

        if (!isset($this->definitions[$key])) {
            $this->definitions[$key] = $definition;
        }

        return '#/definitions/' . $key;
    }

    private function formatProperty($type, $example)
    {
        $val = [
            "example" => $example,
        ];

        if (isset($type['example'])) {
            $val['example'] = $type['example'];
        }

        if (isset($type['type'])) {
            $val['type'] = $type['type'];
        }

        if (isset($type['format'])) {
            $val['format'] = $type['format'];
        }
        return $val;
    }

    private function formatArrayProperty($name, $ref)
    {
        return [
            $name => [
                "type" => 'array',
                "items" => [
                    "\$ref" => "#/definitions/" . $ref
                ]
            ]
        ];
    }

    private function parseModel($class, $pars = null)
    {
        $properties = [];
        $required = [];

        $array_properties = [];

        if (!is_null($pars)) {
            // Per ognuna delle regole
            foreach ($pars as $key => $p) {
                // Se la chiave contiene un punto sto lavorando con un array per ora supportiamo solo la dot notation
                // Salvo la proprietà per mergiarla dopo
                if (strpos($key, '.') !== false) {
                    $exp = explode('.', $key);

                    $buffer = [];

                    $pointer = &$array_properties;

                    foreach ($exp as $pu) {
                        if (!isset($pointer[$pu])) {
                            $pointer[$pu] = [];
                        }
                        $pointer = &$pointer[$pu];
                    }

                    $pointer = $p;

                } else {
                    // TODO: add support for nested array validation
                    // Esplodo i campi di validazione
                    if (is_array($p)) {
                        $explode = $p;
                    } else {
                        $explode = explode('|', $p);
                    }

                    $rule = null;

                    foreach ($explode as $rule) {
                        $rule = $this->getType($rule);
                        if (!is_null($rule)) {
                            break;
                        }
                    }

                    if (is_null($rule)) {
                        $rule = $this->getType('string');
                    }

                    // Check if the key is required
                    $isRequired = in_array('required', $explode);

                    if ($isRequired) {
                        $required[] = $key;
                    }

                    // Add parameter
                    $properties[$key] = $this->formatProperty($rule, 'example value');
                }
            }
        }

        $array_properties_parsed = [];

        // Qui scorro il raw delle proprietà classificate come array
        foreach ($array_properties as $key => $prop) {
            // Per ognuno dei sotto campi faccio qualcosa
            foreach ($prop as $key_p => $prop_p) {
                // Ho trovato una wildcard di accesso, devo generare uno SwaggerArray
                if ($key_p == "*") {
                    $required_inner = [];
                    $properties_inner = [];

                    if (!is_array($prop_p)) {
                        $prop_p = [];
                    }

                    foreach ($prop_p as $keyz => $valz) {
                        $explode = explode('|', $valz);
                        $rule = null;
                        // trovo un tipo per la var
                        foreach ($explode as $rule) {
                            $rule = $this->getType($rule);
                            if (!is_null($rule)) {
                                break;
                            }
                        }
                        if (is_null($rule)) {
                            $rule = $this->getType('string');
                        }

                        // Check if the key is required
                        $isRequired = in_array('required', $explode);

                        if ($isRequired) {
                            $required_inner[] = $keyz;
                        }

                        $properties_inner[$keyz] = $this->formatProperty($rule, 'example value');

                    }


                    $prs = [
                        "type" => "array",
                        "items" => [
                            'title' => $key,
                            'required' => $required_inner,
                            'properties' => $properties_inner,
                        ] // "$ref": "#/definitions/QUALCOSA"
                    ]; // Deve diventare un array req

                    $array_properties_parsed[$key] = $prs;
                }
            }
        }

        $properties = array_merge($properties, $array_properties_parsed);

        return $this->addDefinition($class, $properties, $required);
    }

    private function processTag($name, $description = null)
    {
        if (is_null($description)) {
            $description = 'Auto generated description for ' . $name;
        }

        $tag = [
            'name' => $name,
            'description' => $description,
        ];

        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }
    }

    private function processSecurityDefinition($name, $description, $tag, $in, $type)
    {
        if (!isset($this->securityDefinitions[$name])) {
            $this->securityDefinitions[$name] = [
                "type" => $type,
                "description" => $description,
                "name" => $tag,
                "in" => $in
            ];
        }
    }

    private function processPath($uri, $method, $tag, $summary, $description = null, $parameters = [], $responses = [], $security = [], $name = "", $deprecated = "")
    {
        if ($method == 'HEAD') {
            return;
        }

        $route = [
            'tags' => [
                $tag
            ],
            'summary' => $summary . ($name != "" ? " [$name]" : ""),
            'description' => $description . (is_string($deprecated) ? "\r [DEPRECATION INFO] " . $deprecated : ""),
            'produces' => [
                "application/json"
            ],
            'parameters' => $parameters,
            'responses' => $responses,
            'security' => $security,
            'deprecated' => isset($deprecated) && $deprecated

        ];

        if (!isset($this->paths[$uri])) {
            $this->paths[$uri] = [];
        }

        $this->paths[$uri][strtolower($method)] = $route;
    }

    public function process()
    {
        foreach ($this->enumeratedMiddlewares as $middleware) {
            foreach ($middleware['classes'] as $class) {
                if (isset($class['addon'])) {
                    $addon = $class['addon'];
                    $this->processSecurityDefinition(
                        $middleware['name'],
                        $addon['securityDescription'],
                        $addon['securityParameterName'],
                        $addon['securityParameterIn'],
                        $addon['securityType']
                    );
                }
            }
        }

        /* @var \Ennetech\LaravelRoutingUtilities\Enumerator\Models\EnumeratedRoute $route */
        foreach ($this->enumeratedRoutes as $route) {
            $this->processTag($route->tag, $route->tagDescription);

            $stripedUri = "/" . Str::after($route->uri, $this->basePath);

            // Remove identical methods (eg.: generated by a resource controller)
            if (in_array('PUT', $route->methods) && in_array('PATCH', $route->methods)) {
                unset($route->methods[array_search('PUT', $route->methods)]);
            }

            // Input
            $parameters = [];

            // Query parameters
            foreach ($route->parameters->query ?? [] as $queryParameter) {
                $parameters[] = [
                    'name' => $queryParameter['name'],
                    'in' => 'query',
                    'description' => $queryParameter['description'],
                    'required' => true,
                    'type' => "string"
                ];
            }

            // Path parameters
            foreach ($route->parameters->path ?? [] as $pathParameter) {
                $parameters[] = [
                    'name' => $pathParameter['name'],
                    'in' => 'path',
                    'description' => $pathParameter['description'],
                    'required' => true,
                    'type' => "string"
                ];
            }
            // Body parameters
            if ($route->request && isset($route->requestValidation)) {
                $parameters[] = [
                    'name' => 'body',
                    'in' => 'body',
                    'description' => $route->parameters->body,
                    'schema' => [
                        "\$ref" => $this->parseModel($route->request, $route->requestValidation)
                    ]
                ];
            }

            // Output
            $responses = [];

            $description = "";
            $schema = $this->parseModel($route->response, $route->responseValidation);

            if ($route->isResponseArray) {
                $responses['200'] = [
                    'description' => $description,
                    'schema' => [
                        "type" => 'array',
                        "items" => [
                            '$ref' => $schema
                        ]
                    ]
                ];
            } else {
                $responses['200'] = [
                    'description' => $description,
                    'schema' => [
                        '$ref' => $schema
                    ]
                ];
            }

            $security = [];
            foreach ($route->methods as $method) {
                if (!is_array($route->middlewares)) {
                    $route->middlewares = [$route->middlewares];
                }
                foreach ($route->middlewares as $middleware) {
                    $sanSame = explode(":",$middleware)[0];
                    $hasMiddleware = isset($this->securityDefinitions[$sanSame]) ? $sanSame : false;
                    if ($hasMiddleware) {
                        $security[] = [
                            $hasMiddleware => []
                        ];
                    }
                }

                $this->processPath($stripedUri, $method, $route->tag, $route->summary, $route->description, $parameters, $responses, $security, $route->name, $route->deprecated);
            }
        }
    }

    public function output()
    {
        $this->basePath = $this->config['basepath'];
        $this->process();
        // https://github.com/OAI/OpenAPI-Specification/blob/master/versions/2.0.md
        return [
            'swagger' => '2.0',
            'info' => [
                'title' => $this->config['title'] ?? env('APP_NAME') . ' api docs',
                'description' => $this->config['description'] ?? 'Auto generated api docs',
                'version' => $this->config['version'] ?? '1.0.0',
            ],
            'basePath' => "/" . $this->basePath,
            'paths' => $this->paths,
            'definitions' => $this->definitions,
            'parameters' => $this->parameters,
            'securityDefinitions' => $this->securityDefinitions,
            'tags' => $this->tags,
        ];
    }

    private function getType($commonName)
    {
        $commonName = $commonName == 'numeric' ? 'integer' : $commonName;


        switch ($commonName) {
            case 'email':
                return [
                    'type' => 'string',
                    'example' => 'user@example.com',
                ];
                break;
            case 'integer':
                return [
                    'type' => 'integer',
                    'format' => 'int32',
                    'example' => 1,
                ];
            case 'long':
                return [
                    'type' => 'integer',
                    'format' => 'int64',
                ];
            case 'float':
                return [
                    'type' => 'number',
                    'format' => 'float',
                ];
            case 'double':
                return [
                    'type' => 'number',
                    'format' => 'double',
                ];
            case 'string':
                return [
                    'type' => 'string'
                ];
            case 'byte':
                return [
                    'type' => 'string',
                    'format' => 'byte',
                ];
            case 'binary':
                return [
                    'type' => 'string',
                    'format' => 'binary',
                ];
            case 'boolean':
                return [
                    'type' => 'boolean',
                ];
            case 'date': // 1985-04-12
                return [
                    'type' => 'string',
                    'format' => 'date',
                    'example' => '1985-04-12',
                ];
            case 'dateTime': // 1985-04-12T23:20:50.52Z
                return [
                    'type' => 'string',
                    'format' => 'date-time',
                ];
            case 'password':
                return [
                    'type' => 'string',
                    'format' => 'password',
                ];
        }
        return null;
    }
}
