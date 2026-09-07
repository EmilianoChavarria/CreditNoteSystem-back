<?php

namespace App\Http\Requests\Forecast;

use Illuminate\Foundation\Http\FormRequest;

class StoreForecastChangeRequestBatch extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'            => ['required', 'array', 'min:1', 'max:200'],
            'items.*.idClient' => ['required', 'integer', 'min:1'],
            'items.*.year'     => ['required', 'integer', 'min:2000', 'max:2100'],
            'items.*.month'    => ['required', 'integer', 'min:1', 'max:12'],
            'items.*.amount'   => ['required', 'numeric', 'min:0'],
        ];
    }
}
