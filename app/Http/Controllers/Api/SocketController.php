<?php

namespace App\Http\Controllers\Api;

use App\Events\SocketMessageSent;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SocketController extends Controller
{
    public function broadcast(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'max:40'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos invalidos', $validator->errors(), 422), 422);
        }

        $authUser = $request->attributes->get('authUser');

        $payload = [
            'title' => $request->input('title'),
            'message' => $request->input('message'),
            'type' => $request->input('type', 'info'),
            'sentByUserId' => $authUser?->id,
            'sentAt' => now()->toIso8601String(),
        ];

        broadcast(new SocketMessageSent($payload));

        return response()->json(ApiResponse::success('Mensaje emitido por socket', $payload));
    }
}
