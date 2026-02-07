<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProtectedController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->attributes->get('authUser');

        return response()->json(ApiResponse::success('Perfil', [
            'id' => $user->id,
            'fullName' => $user->fullName,
            'email' => $user->email,
            'roleId' => $user->roleId,
            'roleName' => $user->roleName,
        ]));
    }

    public function adminOnly()
    {
        return response()->json(ApiResponse::success('Acceso administrador', [
            'message' => 'Ruta protegida por rol administrador',
        ]));
    }
}
