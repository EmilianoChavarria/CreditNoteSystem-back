<?php

namespace App\Http\Requests\Distributors;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistributorForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year'             => ['required', 'integer', 'min:2000', 'max:2100'],
            'months'           => ['required', 'array', 'min:1', 'max:12'],
            'months.*.month'   => ['required', 'integer', 'min:1', 'max:12'],
            'months.*.forecast'=> ['required', 'integer', 'min:0'],
            'months.*.sales'   => ['required', 'integer', 'min:0'],
        ];
    }
}
