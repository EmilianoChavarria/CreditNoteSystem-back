<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordRequirement;
use App\Support\ApiResponse;
use App\Services\PasswordValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PasswordRequirementsController extends Controller
{
    protected PasswordValidationService $passwordService;

    public function __construct(PasswordValidationService $passwordService)
    {
        $this->passwordService = $passwordService;
    }

    /**
     * Obtener los requisitos actuales de contraseña
     */
    public function getRequirements(Request $request)
    {
        $requirements = $this->passwordService->getRequirements();

        if (!$requirements) {
            return response()->json(ApiResponse::error('Configuración de requisitos no encontrada', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Requisitos de contraseña obtenidos', $requirements));
    }

    /**
     * Actualizar los requisitos de contraseña (solo admin)
     */
    public function updateRequirements(Request $request)
    {   

        $existRequirements = $this->passwordService->getRequirements();

        $admin = $request->attributes->get('authUser');
        
        if (!$admin || $admin->roleId != 1) { // Ajusta la validación de rol según tu implementación
            return response()->json(ApiResponse::error('No autorizado. Solo administradores pueden actualizar requisitos', null, 403), 403);
        }

        $validator = Validator::make($request->all(), [
            'minLength' => ['integer', 'min:6', 'max:128'],
            'requireUppercase' => ['boolean'],
            'requireLowercase' => ['boolean'],
            'requireNumbers' => ['boolean'],
            'requireSpecialChars' => ['boolean'],
            // 'allowedSpecialChars' => ['string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        try {
            $updateData = $request->only([
                'minLength',
                'requireUppercase',
                'requireLowercase',
                'requireNumbers',
                'requireSpecialChars',
            ]);

            $updateData['createdAt'] = $existRequirements ? $existRequirements->createdAt : now();
            $updateData['updatedAt'] = now();

            PasswordRequirement::updateOrCreate(
                ['id' => 1],
                $updateData
            );

            return response()->json(ApiResponse::success(
                'Requisitos de contraseña actualizados correctamente',
                $this->passwordService->getRequirements()
            ));
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al actualizar requisitos', $e->getMessage(), 500), 500);
        }
    }

    /**
     * Validar una contraseña contra los requisitos actuales
     */
    public function validatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Contraseña requerida', $validator->errors(), 422), 422);
        }

        $password = $request->input('password');
        $errors = $this->passwordService->validatePassword($password);
        $requirements = $this->passwordService->getRequirements();

        if (empty($errors)) {
            return response()->json(ApiResponse::success(
                'La contraseña cumple con todos los requisitos',
                [
                    'isValid' => true,
                    'requirements' => $requirements,
                ]
            ));
        }

        return response()->json(ApiResponse::error(
            'La contraseña no cumple con los requisitos',
            [
                'isValid' => false,
                'requirements' => $requirements,
            ],
            [
                'errors' => $errors
            ],
            422
        ), 422);
    }

    /**
     * Obtener los requisitos en formato amigable para el frontend
     */
    public function getRequirementsFormatted(Request $request)
    {
        $requirements = $this->passwordService->getRequirements();

        if (!$requirements) {
            return response()->json(ApiResponse::error('Configuración no encontrada', null, 404), 404);
        }

        $formatted = [
            'minLength' => [
                'label' => 'Longitud mínima',
                'value' => $requirements->minLength,
                'description' => "La contraseña debe tener al menos {$requirements->minLength} caracteres",
            ],
            'requireUppercase' => [
                'label' => 'Mayúsculas requeridas',
                'value' => (bool) $requirements->requireUppercase,
                'description' => 'La contraseña debe contener al menos una mayúscula (A-Z)',
            ],
            'requireLowercase' => [
                'label' => 'Minúsculas requeridas',
                'value' => (bool) $requirements->requireLowercase,
                'description' => 'La contraseña debe contener al menos una minúscula (a-z)',
            ],
            'requireNumbers' => [
                'label' => 'Números requeridos',
                'value' => (bool) $requirements->requireNumbers,
                'description' => 'La contraseña debe contener al menos un número (0-9)',
            ],
            'requireSpecialChars' => [
                'label' => 'Caracteres especiales requeridos',
                'value' => (bool) $requirements->requireSpecialChars,
                'description' => "La contraseña debe contener al menos uno de: {$requirements->allowedSpecialChars}",
                'allowedChars' => $requirements->allowedSpecialChars,
            ]
        ];

        return response()->json(ApiResponse::success('Requisitos formateados obtenidos', $formatted));
    }
}
