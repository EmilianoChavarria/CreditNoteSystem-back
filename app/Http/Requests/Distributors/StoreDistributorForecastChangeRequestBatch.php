<?php

namespace App\Http\Requests\Distributors;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistributorForecastChangeRequestBatch extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                 => ['required', 'array', 'min:1', 'max:200'],
            'items.*.distributorId' => ['required', 'integer', 'min:1'],
            'items.*.year'          => ['required', 'integer', 'min:2000', 'max:2100'],
            'items.*.month'         => ['required', 'integer', 'min:1', 'max:12'],
            'items.*.forecast'      => ['required', 'integer', 'min:0'],
        ];
    }
}
