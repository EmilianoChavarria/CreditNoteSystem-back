<?php

namespace App\Http\Requests\ChargePolicies;

use Illuminate\Foundation\Http\FormRequest;

class StoreChargePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'day'        => ['required', 'integer', 'min:1'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
