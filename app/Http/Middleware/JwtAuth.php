<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class JwtAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractBearerToken($request);

        if (!$token) {
            return response()->json(ApiResponse::error('Token no proporcionado', null, 401), 401);
        }

        try {
            $jwt = app(JwtService::class)->decodeToken($token);
        } catch (Throwable) {
            return response()->json(ApiResponse::error('Token inválido o expirado', null, 401), 401);
        }

        $user = DB::table('users')
            ->leftJoin('roles', 'users.roleId', '=', 'roles.id')
            ->select('users.*', 'roles.roleName')
            ->where('users.id', (int) $jwt->sub)
            ->first();

        if (!$user || !$user->isActive || $user->deletedAt) {
            return response()->json(ApiResponse::error('Usuario no autorizado', null, 403), 403);
        }

        $security = DB::table('userSecurity')->where('userId', $user->id)->first();

        if ($security && $security->lockedUntil && Carbon::parse($security->lockedUntil)->isFuture()) {
            return response()->json(ApiResponse::error('Usuario bloqueado temporalmente', null, 423), 423);
        }

        if (!$security || $security->sessionToken !== $token) {
            return response()->json(ApiResponse::error('Sesión no válida', null, 401), 401);
        }

        DB::table('userSecurity')
            ->where('userId', $user->id)
            ->update([
                'lastActivityAt' => Carbon::now(),
                'lastKnownIp' => $request->ip(),
            ]);

        $request->attributes->set('authUser', $user);
        $request->attributes->set('authRole', $user->roleName);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return trim(substr($header, 7));
    }
}
