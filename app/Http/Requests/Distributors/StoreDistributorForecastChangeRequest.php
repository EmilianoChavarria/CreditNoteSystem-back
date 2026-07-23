<?php

namespace App\Http\Requests\Distributors;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistributorForecastChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'distributorId' => ['required', 'integer', 'min:1'],
            'year'          => ['required', 'integer', 'min:2000', 'max:2100'],
            'month'         => ['required', 'integer', 'min:1', 'max:12'],
            'forecast'      => ['required', 'integer', 'min:0'],
        ];
    }
}
