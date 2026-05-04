<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlockedIpResource;
use App\Http\Resources\BlockedUserResource;
use App\Models\BlockedIp;
use App\Models\IpBlockedHistory;
use App\Models\User;
use App\Models\UserBlockedHistory;
use App\Models\UserSecurity;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class AdminSecurityController extends Controller
{
    public function blockedUsers(Request $request)
    {
        $admin = $request->attributes->get('authUser');

        if (!$admin || (string) $admin->roleName !== 'ADMIN') {
            return response()->json(ApiResponse::error('No autorizado. Solo administradores pueden ver esta información', null, 403), 403);
        }

        $perPage = max(1, (int) $request->query('perPage', 15));

        $users = User::with(['role', 'security'])
            ->whereHas('security', function ($query) {
                $query->whereNotNull('lockedUntil')
                    ->where('lockedUntil', '>', Carbon::now());
            })
            ->leftJoinSub(
                UserBlockedHistory::query()
                    ->selectRaw('userId, reason, action, createdAt, ROW_NUMBER() OVER (PARTITION BY userId ORDER BY createdAt DESC) as rn')
                    ->where('action', 'blocked'),
                'block_history',
                function ($join) {
                    $join->on('users.id', '=', 'block_history.userId')
                        ->where('block_history.rn', '=', 1);
                }
            )
            ->select('users.*')
            ->addSelect('block_history.reason', 'block_history.createdAt as blockedAt')
            ->orderBy('fullName')
            ->paginate($perPage);

        $users->setCollection(BlockedUserResource::collection($users->getCollection())->collection);

        return response()->json(ApiResponse::success('Usuarios bloqueados obtenidos correctamente', $users));
    }

    public function blockedIps(Request $request)
    {
        $admin = $request->attributes->get('authUser');

        if (!$admin || (string) $admin->roleName !== 'ADMIN') {
            return response()->json(ApiResponse::error('No autorizado. Solo administradores pueden ver esta información', null, 403), 403);
        }

        $perPage = max(1, (int) $request->query('perPage', 15));

        $ips = BlockedIp::query()
            ->where('isBlockedPermanently', true)
            ->leftJoinSub(
                IpBlockedHistory::query()
                    ->selectRaw('ipAddress, reason, action, createdAt, ROW_NUMBER() OVER (PARTITION BY ipAddress ORDER BY createdAt DESC) as rn')
                    ->where('action', 'blocked'),
                'ip_history',
                function ($join) {
                    $join->on('blockedips.ipAddress', '=', 'ip_history.ipAddress')
                        ->where('ip_history.rn', '=', 1);
                }
            )
            ->select('blockedips.*')
            ->addSelect('ip_history.reason', 'ip_history.createdAt as blockedHistoryAt')
            ->orderByDesc('blockedAt')
            ->paginate($perPage);

        $ips->setCollection(BlockedIpResource::collection($ips->getCollection())->collection);

        return response()->json(ApiResponse::success('IPs bloqueadas obtenidas correctamente', $ips));
    }

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
