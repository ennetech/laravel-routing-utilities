# laravel-routing-utilities
Defining routes is a little bit tricky once the number of endpoints grow up, why not emulate symfony method for declaring routes in laravel?

## Usage:
- install the package with ```composer require ennetech/laravel-routing-utilities```
- autoload a namespace in RouteServiceProvider like this: 
```
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(function () {
                RouteAutoloader::loadNamespace('App\Http\Controllers\Api\v1');
            });
```
- annotate the methods (method and path are mandatory):
```
    /**
     * @method GET
     * @path /
     * @where id=[0-9]+
     * @middleware throttle
     * @name example
     */
```

# Missing docs:
- Resource controller autoloading
- Auto documentation

# LICENSE
MIT
