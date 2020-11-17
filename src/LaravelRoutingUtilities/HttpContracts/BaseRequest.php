<?php

namespace Ennetech\LaravelRoutingUtilities\HttpContracts;

class BaseRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [];
    }
}
