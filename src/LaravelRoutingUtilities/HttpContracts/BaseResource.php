<?php

namespace Ennetech\LaravelRoutingUtilities\HttpContracts;

use Illuminate\Http\Resources\Json\JsonResource;

class BaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }

    public function __construct($resource)
    {
        parent::__construct($resource);
        self::wrap('');
    }

    protected function extractRulesFromRequest($class)
    {
        $requestIstance = new $class;
        return $requestIstance->rules();
    }
}
