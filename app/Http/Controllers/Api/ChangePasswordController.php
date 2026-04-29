<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class ChangePasswordController extends Controller
{
    public function changePassword(Request $request)
    {
        $user = $request->attributes->get('authUser');

        if (!$user) {
            return response()->json(ApiResponse::error('Sesión no válida', null, 401), 401);
        }

        $rules = [
            'newPassword' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ];

        if (!$user->mustChangePassword) {
            $rules['currentPassword'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);

        $currentPassword = $user->mustChangePassword
            ? env('DEFAULT_PASSWORD')
            : $validated['currentPassword'];

        if (!Hash::check($currentPassword, $user->passwordHash)) {
            return response()->json(ApiResponse::error('Contraseña actual incorrecta', null, 422), 422);
        }

        DB::table('users')->where('id', $user->id)->update([
            'passwordHash'       => Hash::make($validated['newPassword']),
            'mustChangePassword' => false,
        ]);

        return response()->json(ApiResponse::success('Contraseña actualizada'), 200);
    }
}
