<?php

namespace App\Http\Requests\Forecast;

use Illuminate\Foundation\Http\FormRequest;

class UpdateForecastEmailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emails'   => ['required', 'array', 'min:1'],
            'emails.*' => ['required', 'email'],
        ];
    }
}
