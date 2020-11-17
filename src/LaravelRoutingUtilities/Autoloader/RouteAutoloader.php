<?php

namespace Ennetech\LaravelRoutingUtilities\Autoloader;

use Ennetech\LaravelRoutingUtilities\DocBlockReader;
use Illuminate\Support\Str;

class RouteAutoloader
{
    static public function loadNamespace($namespace, $folderPath = null)
    {
        if (!$folderPath) {
            $folderPath = self::generatePathFromNamespace($namespace);
        }

        $files_in_folder = collect(self::scanFolder($folderPath));

        $controllers = $files_in_folder->map(function ($file) use ($namespace) {
            $class = $file['class_name'];
            $path = $file['file_path'];

            $file_content = file_get_contents($path);

            $functions = collect(self::listFunctionsForFile($file_content));

            $functions = $functions->map(function ($name) use ($class, $namespace) {

                return [
                    'name' => $name,
                    'parameters' => self::getFunctionParameters($namespace . '\\' . $class, $name)
                ];
            });

            $file['namespaced_class'] = $namespace . '\\' . $class;
            $file['parameters'] = self::getClassParameters($namespace . '\\' . $class);
            $file['functions'] = $functions;
            return $file;
        });

        self::autoload($namespace, $controllers);

        return $controllers;
    }

    static public function generatePathFromNamespace($namespace)
    {
        $ds = DIRECTORY_SEPARATOR;

        // Replace class path PSR-4
        $namespaceSanitized = str_replace("\\", $ds, $namespace);

        $folderPath = base_path($namespaceSanitized);

        // Some love for default namespace in laravel
        $folderPath = str_replace($ds . "App" . $ds, $ds . "app" . $ds, $folderPath);

        return $folderPath . $ds;
    }

    static public function scanFolder($folderPath)
    {
        // Where to search
        $path = $folderPath . '*.php';
        $files = collect(glob($path));

        $files = $files->map(function ($path) use ($folderPath) {

            // Now we have to sanitize the names, a full path become a class name
            $one = Str::after($path, $folderPath);
            $sanitized_name = Str::replaceLast(".php", "", $one);

            return [
                'file_path' => $path,
                'class_name' => $sanitized_name
            ];
        });

        return $files->values();
    }

    static public function listFunctionsForFile($content)
    {
        // Regex to match functions
        $searchRegex = '/function *(?P<name>[a-zA-Z0-9-]+)\(/';
        preg_match_all($searchRegex, $content, $methods);

        return $methods['name'];
    }

    static public function getFunctionParameters($class_name, $function_name)
    {
        $reader = new DocBlockReader($class_name, $function_name, 'method');
        return $reader->getParameters();
    }

    static public function getClassParameters($class_name)
    {
        $reader = new DocBlockReader($class_name);
        return $reader->getParameters();
    }

    static public function autoload($namespace, $scanned)
    {
        // Now it's time for route registration, one file at a time
        foreach ($scanned as $route_group) {

            // Sanitize middleware for controller-group
            if (isset($route_group['parameters']['middleware'])) {
                $exploded = explode("|", $route_group['parameters']['middleware']);
                if (sizeof($exploded) != 1) {
                    $route_group['parameters']['middleware'] = $exploded;
                }
            }

            if (!isset($route_group['parameters']['prefix'])) {
                $route_group['parameters']['prefix'] = Str::kebab(Str::before($route_group['class_name'], 'Controller'));
            }

            $parameters = $route_group['parameters'];

            $mw = isset($route_group['parameters']['middleware']) ? $route_group['parameters']['middleware'] : null;

            if (isset($parameters['resource'])) {
                switch ((string)$parameters['resource']) {
                    case 'web':
                        \Route::resource($parameters['prefix'], $route_group['namespaced_class'])->middleware($mw);
                        break;
                    default:
                        \Route::apiResource($parameters['prefix'], $route_group['namespaced_class'])->middleware($mw);
                }
            }

            // Each controller file is a separate group
            \Route::group($parameters, function () use ($route_group) {
                // This is the name of the controller class
                $sanitized_name = $route_group['namespaced_class'];

                // Each controller method is a separate route
                // "name"
                // "parameters"
                foreach ($route_group['functions'] as $route_path) {
                    $parameters = $route_path['parameters'];

                    if (isset($parameters['method']) && isset($parameters['path'])) {
                        $controller_name = $route_path['name'];
                        $cname = $sanitized_name . '@' . $controller_name;
                        $verb = strtolower($parameters['method']);
                        $exploded = explode("|", $verb);
                        if (sizeof($exploded) == 1) {
                            $route = \Route::$verb($parameters['path'], $cname);
                        } else {
                            $route = \Route::match($exploded, $parameters['path'], $cname);
                        }

                        if (isset($parameters['name'])) {
                            $route->name($parameters['name']);
                        } else {
                            $generated_name = Str::kebab(Str::before($route_group['class_name'], 'Controller') . '.' . $route_path['name']);
                            $route->name($generated_name);
                        }

                        if (isset($parameters['where'])) {
                            $exploded = explode("|", $parameters['where']);

                            $conditions = [];
                            foreach ($exploded as $rule) {
                                $exp = explode("=", $rule);
                                $key = $exp[0];
                                $value = $exp[1];
                                $conditions[$key] = $value;
                            }

                            $route->where($conditions);
                        }

                        if (isset($parameters['middleware'])) {
                            $route->middleware($parameters['middleware']);
                        }

                    }
                }
            });
        }
    }
}
