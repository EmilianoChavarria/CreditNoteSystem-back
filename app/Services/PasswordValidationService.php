<?php

namespace App\Services;

use App\Models\PasswordRequirement;

class PasswordValidationService
{
    public function getRequirements()
    {
        return PasswordRequirement::first();
    }

    public function validatePassword(string $password, ?object $requirements = null): array
    {
        $requirements = $requirements ?? $this->getRequirements();
        $errors = [];

        // Validar longitud mínima
        if (strlen($password) < $requirements->minLength) {
            $errors[] = "La contraseña debe tener al menos {$requirements->minLength} caracteres";
        }

        // Validar mayúsculas
        if ($requirements->requireUppercase && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una mayúscula';
        }

        // Validar minúsculas
        if ($requirements->requireLowercase && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una minúscula';
        }

        // Validar números
        if ($requirements->requireNumbers && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un número';
        }

        // Validar caracteres especiales
        if ($requirements->requireSpecialChars) {
            $specialCharsPattern = preg_quote($requirements->allowedSpecialChars);
            if (!preg_match("/[{$specialCharsPattern}]/", $password)) {
                $errors[] = "La contraseña debe contener al menos uno de estos caracteres especiales: {$requirements->allowedSpecialChars}";
            }
        }

        return $errors;
    }

    public function isPasswordValid(string $password, ?object $requirements = null): bool
    {
        return empty($this->validatePassword($password, $requirements));
    }
}
