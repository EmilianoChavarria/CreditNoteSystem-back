<?php

namespace App\Http\Requests\Distributors;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDistributorForecastMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'forecast' => ['required_without:sales', 'nullable', 'integer', 'min:0'],
            'sales'    => ['required_without:forecast', 'nullable', 'integer', 'min:0'],
        ];
    }
}
