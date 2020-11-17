<?php

namespace Ennetech\LaravelRoutingUtilities\HttpContracts;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BaseResourceCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection;
    }
}
