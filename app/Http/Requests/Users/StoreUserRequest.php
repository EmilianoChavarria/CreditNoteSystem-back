<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->shouldClearSupervisorId($this->input('supervisorId'))) {
            $this->merge(['supervisorId' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'supervisorId' => ['nullable', 'integer', 'exists:users,id'],
            'preferredLanguage' => ['nullable', Rule::in(['en', 'es'])],
            'isActive' => ['nullable', 'boolean'],
        ];
    }

    private function shouldClearSupervisorId(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['', '0', 'null', 'undefined'], true);
        }

        return (int) $value === 0;
    }
}
