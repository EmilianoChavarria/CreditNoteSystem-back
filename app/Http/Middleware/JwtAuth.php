<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use App\Services\LoginAttemptSettingsService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class JwtAuth
{
    public function __construct(
        private readonly LoginAttemptSettingsService $loginAttemptSettingsService
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractToken($request);
        $isVerifyRoute = $this->isVerifyRoute($request);
        $sessionTimeoutMinutes = (int) $this->loginAttemptSettingsService->getSettings()->sessionTimeoutMinutes;

        if (!$token) {
            if ($isVerifyRoute) {
                $request->attributes->set('authTokenValid', false);
                $request->attributes->set('authToken', null);

                return $next($request);
            }

            return response()->json(ApiResponse::error('Token no proporcionado', null, 401), 401);
        }

        try {
            $jwt = app(JwtService::class)->decodeToken($token);
        } catch (Throwable) {
            if ($isVerifyRoute) {
                $request->attributes->set('authTokenValid', false);
                $request->attributes->set('authToken', $token);

                return $next($request);
            }

            return response()->json(ApiResponse::error('Token inválido o expirado', null, 401), 401);
        }

        $user = DB::table('users')
            ->leftJoin('roles', 'users.roleId', '=', 'roles.id')
            ->select('users.*', 'roles.roleName')
            ->where('users.id', (int) $jwt->sub)
            ->first();

        if (!$user || !$user->isActive || $user->deletedAt) {
            if ($isVerifyRoute) {
                $request->attributes->set('authTokenValid', false);
                $request->attributes->set('authToken', $token);

                return $next($request);
            }

            return response()->json(ApiResponse::error('Usuario no autorizado', null, 403), 403);
        }

        $security = DB::table('userSecurity')->where('userId', $user->id)->first();

        if ($security && $security->lockedUntil && Carbon::parse($security->lockedUntil)->isFuture()) {
            if ($isVerifyRoute) {
                $request->attributes->set('authTokenValid', false);
                $request->attributes->set('authToken', $token);

                return $next($request);
            }

            return response()->json(ApiResponse::error('Usuario bloqueado temporalmente', null, 423), 423);
        }

        if ($security && $security->lastActivityAt) {
            $lastActivityAt = Carbon::parse($security->lastActivityAt);

            if ($lastActivityAt->addMinutes($sessionTimeoutMinutes)->isPast()) {
                DB::table('userSecurity')
                    ->where('userId', $user->id)
                    ->update([
                        'sessionToken' => null,
                        'lastActivityAt' => Carbon::now(),
                    ]);

                if ($isVerifyRoute) {
                    $request->attributes->set('authTokenValid', false);
                    $request->attributes->set('authToken', $token);

                    return $next($request);
                }

                return response()->json(ApiResponse::error('Sesión expirada', null, 401), 401);
            }
        }

        if (!$security || $security->sessionToken !== $token) {
            if ($isVerifyRoute) {
                $request->attributes->set('authTokenValid', false);
                $request->attributes->set('authToken', $token);

                return $next($request);
            }

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
        $request->attributes->set('authTokenValid', true);
        $request->attributes->set('authToken', $token);
        $request->attributes->set('sessionTimeoutMinutes', $sessionTimeoutMinutes);
        $request->attributes->set('sessionLastActivityAt', Carbon::now());
        $request->setUserResolver(fn() => $user);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {

        $header = $request->header('Authorization');
        if ($header && str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return $request->cookie('access_token');
    }

    private function isVerifyRoute(Request $request): bool
    {
        return str_ends_with($request->path(), 'auth/verify');
    }
}
