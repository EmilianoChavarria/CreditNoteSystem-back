<?php

namespace App\Http\Requests\Forecast;

use Illuminate\Foundation\Http\FormRequest;

class StoreForecastChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idClient' => ['required', 'integer', 'min:1'],
            'year'     => ['required', 'integer', 'min:2000', 'max:2100'],
            'month'    => ['required', 'integer', 'min:1', 'max:12'],
            'amount'   => ['required', 'numeric', 'min:0'],
        ];
    }
}
