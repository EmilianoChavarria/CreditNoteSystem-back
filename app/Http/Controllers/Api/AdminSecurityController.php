<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\IpBlockedHistory;
use App\Models\UserBlockedHistory;
use App\Models\UserSecurity;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class AdminSecurityController extends Controller
{
    public function unlockUser(Request $request, int $id)
    {
        $admin = $request->attributes->get('authUser');
        $security = UserSecurity::where('userId', $id)->first();

        if (!$security) {
            return response()->json(ApiResponse::error('Usuario no encontrado', null, 404), 404);
        }

        $security->update([
            'failedAttempts' => 0,
            'lastFailedAt' => null,
            'lockedUntil' => null,
        ]);

        UserBlockedHistory::create([
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
        $record = BlockedIp::where('ipAddress', $ip)->first();

        if (!$record) {
            return response()->json(ApiResponse::error('IP no encontrada', null, 404), 404);
        }

        $record->update([
            'failedAttempts' => 0,
            'isBlockedPermanently' => false,
            'releasedAt' => Carbon::now(),
        ]);

        IpBlockedHistory::create([
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
