<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:6', 'different:currentPassword', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'newPassword.confirmed' => 'La confirmación de contraseña no coincide.',
        ];
    }
}