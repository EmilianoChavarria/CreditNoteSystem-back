<?php

namespace App\Http\Requests\ChargePolicies;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChargePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conditional' => ['required', 'string'],
            'day'         => ['sometimes', 'integer', 'min:1'],
            'percentage'  => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
