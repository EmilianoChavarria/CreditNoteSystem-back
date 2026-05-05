<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoginAttemptSettingResource;
use App\Models\LoginAttemptSetting;
use App\Services\LoginAttemptSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoginAttemptSettingsController extends Controller
{
    public function __construct(
        private readonly LoginAttemptSettingsService $loginAttemptSettingsService
    ) {
    }

    public function getSettings(Request $request)
    {
        $admin = $request->attributes->get('authUser');

        if (!$admin || (string) $admin->roleName !== "ADMIN") {
            return response()->json(ApiResponse::error('No autorizado. Solo administradores pueden ver esta configuración', null, 403), 403);
        }

        return response()->json(ApiResponse::success('Configuración de intentos obtenida', LoginAttemptSettingResource::make($this->loginAttemptSettingsService->getSettings())));
    }

    public function updateSettings(Request $request)
    {
        $admin = $request->attributes->get('authUser');
        // var_dump($admin);
        if (!$admin || (string) $admin->roleName !== "ADMIN") {
            return response()->json(ApiResponse::error('No autorizado. Solo administradores pueden actualizar esta configuración', null, 403), 403);
        }

        $validator = Validator::make($request->all(), [
            'maxUserAttempts' => ['required', 'integer', 'min:1', 'max:1000'],
            'maxIpAttempts' => ['required', 'integer', 'min:1', 'max:1000'],
            'sessionTimeoutMinutes' => ['required', 'integer', 'min:1', 'max:10080'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $settings = LoginAttemptSetting::query()->first();

        if ($settings) {
            $settings->update([
                'maxUserAttempts' => (int) $request->input('maxUserAttempts'),
                'maxIpAttempts' => (int) $request->input('maxIpAttempts'),
                'sessionTimeoutMinutes' => (int) $request->input('sessionTimeoutMinutes'),
                'updatedAt' => now(),
            ]);
        } else {
            $settings = LoginAttemptSetting::create([
                'maxUserAttempts' => (int) $request->input('maxUserAttempts'),
                'maxIpAttempts' => (int) $request->input('maxIpAttempts'),
                'sessionTimeoutMinutes' => (int) $request->input('sessionTimeoutMinutes'),
                'createdAt' => now(),
                'updatedAt' => now(),
            ]);
        }

        return response()->json(ApiResponse::success('Configuración de intentos actualizada correctamente', LoginAttemptSettingResource::make($settings)));
    }
}