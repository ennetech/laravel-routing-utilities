<?php

namespace Ennetech\LaravelRoutingUtilities\Enumerator\Models;


class RouteParameters
{
    public $path = [];
    public $body = "Payload";
    public $query = [];

    public function add($where, $name, $required = false, $rules = [], $description = null)
    {
        $description = isset($description) ? $description : $where . " parameter";
        $this->$where[] = [
            'name' => $name,
            'description' => $description,
            'required' => $required,
            'rules' => $rules
        ];
    }
}