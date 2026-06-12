<?php

namespace App\Http\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectMassRequestInput extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selectAll'                  => ['boolean'],
            'requestIds'                 => [Rule::requiredIf(fn () => !$this->boolean('selectAll')), 'array', 'min:1'],
            'requestIds.*'               => ['integer', 'distinct'],
            'filters'                    => ['sometimes', 'array'],
            'filters.requestTypeId'      => ['nullable', 'integer'],
            'filters.search'             => ['nullable', 'string', 'max:500'],
            'filters.roleName'           => ['nullable', 'string', 'max:100'],
            'filters.requesterId'        => ['nullable', 'integer'],
            'filters.classificationType' => ['nullable', 'string', 'max:100'],
            'filters.dateFrom'           => ['nullable', 'date_format:Y-m-d'],
            'filters.dateTo'             => ['nullable', 'date_format:Y-m-d'],
            'comments'                   => ['required', 'string', 'max:7000'],
        ];
    }
}
