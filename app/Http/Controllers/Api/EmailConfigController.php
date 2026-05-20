<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailConfig;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailConfigController extends Controller
{
    public function get()
    {
        $config = EmailConfig::find(1);

        if (!$config) {
            return response()->json(ApiResponse::error('Configuración no encontrada', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Configuración obtenida', $config));
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emailSupport'  => ['required', 'email', 'max:255'],
            'emailMode'     => ['sometimes', 'in:normal,override,disabled'],
            'overrideEmail' => ['nullable', 'email', 'max:255', 'required_if:emailMode,override'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $existing = EmailConfig::find(1);

        EmailConfig::updateOrCreate(
            ['id' => 1],
            [
                'emailSupport'  => $request->input('emailSupport'),
                'emailMode'     => $request->input('emailMode', $existing?->emailMode ?? 'normal'),
                'overrideEmail' => $request->input('overrideEmail', $existing?->overrideEmail),
                'createdAt'     => $existing ? $existing->createdAt : now(),
                'updatedAt'     => now(),
            ]
        );

        return response()->json(ApiResponse::success(
            'Configuración guardada correctamente',
            EmailConfig::find(1)
        ));
    }
}
