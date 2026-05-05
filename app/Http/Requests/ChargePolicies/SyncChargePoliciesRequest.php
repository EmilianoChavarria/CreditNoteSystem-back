<?php

namespace App\Http\Requests\ChargePolicies;

use Illuminate\Foundation\Http\FormRequest;

class SyncChargePoliciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'policies'                  => ['required', 'array', 'min:1'],
            'policies.*.id'             => ['nullable', 'integer', 'exists:chargePolicies,id'],
            'policies.*.conditional'    => ['required', 'string'],
            'policies.*.day'            => ['required', 'integer', 'min:1'],
            'policies.*.percentage'     => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
