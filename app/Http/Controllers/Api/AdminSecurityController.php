<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminSecurityController extends Controller
{
    public function unlockUser(Request $request, int $id)
    {
        $admin = $request->attributes->get('authUser');
        $security = DB::table('userSecurity')->where('userId', $id)->first();

        if (!$security) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        DB::table('userSecurity')->where('userId', $id)->update([
            'failedAttempts' => 0,
            'lastFailedAt' => null,
            'lockedUntil' => null,
        ]);

        DB::table('userBlockedHistory')->insert([
            'userId' => $id,
            'action' => 'unblocked',
            'reason' => 'Desbloqueo manual por administrador',
            'ipAddress' => null,
            'adminUserId' => $admin?->id,
            'createdAt' => Carbon::now(),
        ]);

        return response()->json(ApiResponse::success('Usuario desbloqueado'));
    }

    public function unlockIp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ipAddress' => ['required', 'string', 'max:45'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $ip = $request->input('ipAddress');
        $admin = $request->attributes->get('authUser');
        $record = DB::table('blockedIps')->where('ipAddress', $ip)->first();

        if (!$record) {
            return response()->json(ApiResponse::error('IP no encontrada', null, 404), 404);
        }

        DB::table('blockedIps')->where('ipAddress', $ip)->update([
            'failedAttempts' => 0,
            'isBlockedPermanently' => false,
            'releasedAt' => Carbon::now(),
        ]);

        DB::table('ipBlockedHistory')->insert([
            'ipAddress' => $ip,
            'action' => 'unblocked',
            'reason' => 'Desbloqueo manual por administrador',
            'userId' => null,
            'adminUserId' => $admin?->id,
            'createdAt' => Carbon::now(),
        ]);

        return response()->json(ApiResponse::success('IP desbloqueada'));
    }
}
