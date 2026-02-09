<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JwtService;
use App\Services\PasswordValidationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    protected PasswordValidationService $passwordService;

    public function __construct(PasswordValidationService $passwordService)
    {
        $this->passwordService = $passwordService;
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'supervisorId' => ['nullable', 'integer', 'exists:users,id'],
            'preferredLanguage' => ['nullable', Rule::in(['en', 'es'])],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        // Validar contraseña según los requisitos
        $password = $request->input('password');
        $passwordErrors = $this->passwordService->validatePassword($password);

        if (!empty($passwordErrors)) {
            $requirements = $this->passwordService->getRequirements();
            return response()->json(
                ApiResponse::error(
                    'La contraseña no cumple con los requisitos',
                    [
                        'errors' => $passwordErrors,
                        'requirements' => $requirements,
                    ],
                    422
                ),
                422
            );
        }

        $now = Carbon::now();
        $passwordHash = Hash::make($password);

        $id = DB::table('users')->insertGetId([
            'fullName' => $request->input('fullName'),
            'email' => $request->input('email'),
            'passwordHash' => $passwordHash,
            'roleId' => (int) $request->input('roleId'),
            'supervisorId' => $request->input('supervisorId'),
            'preferredLanguage' => $request->input('preferredLanguage', 'en'),
            'isActive' => true,
            'deletedAt' => null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);

        DB::table('userSecurity')->insert([
            'userId' => $id,
            'failedAttempts' => 0,
            'lastFailedAt' => null,
            'lockedUntil' => null,
            'lastKnownIp' => null,
            'sessionToken' => null,
            'lastActivityAt' => null,
            'lastLoginAt' => null,
        ]);

        return response()->json(
            ApiResponse::success('Usuario registrado', [
                'id' => $id,
                'fullName' => $request->input('fullName'),
                'email' => $request->input('email'),
                'roleId' => (int) $request->input('roleId'),
            ], 201),
            201
        );
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json(
                ApiResponse::error('Datos inválidos', $validator->errors(), 422),
                422
            );
        }

        $ip = $request->ip();
        $now = Carbon::now();
        $maxUserAttempts = (int) config('security.max_user_attempts');
        $maxIpAttempts = (int) config('security.max_ip_attempts');
        $lockMinutes = (int) config('security.user_lock_minutes');

        $ipRecord = $this->getOrCreateIpRecord($ip);

        if ($this->isIpBlocked($ipRecord)) {
            return response()->json(ApiResponse::error('Dirección IP bloqueada permanentemente', null, 423), 423);
        }

        $user = DB::table('users')
            ->leftJoin('roles', 'users.roleId', '=', 'roles.id')
            ->select('users.*', 'roles.roleName')
            ->where('users.email', $request->input('email'))
            ->first();

        if (!$user || !$user->isActive || $user->deletedAt) {
            $this->registerIpFailure($ip, $maxIpAttempts, $now);

            if ($this->isIpBlocked($this->getOrCreateIpRecord($ip))) {
                return response()->json(ApiResponse::error('Dirección IP bloqueada permanentemente', null, 423), 423);
            }

            return response()->json(ApiResponse::error('Credenciales inválidas', null, 401), 401);
        }

        $security = $this->getOrCreateUserSecurity($user->id);

        if ($security->lockedUntil && Carbon::parse($security->lockedUntil)->isFuture()) {
            return response()->json(ApiResponse::error('Usuario bloqueado temporalmente', null, 423), 423);
        }

        if (!Hash::check($request->input('password'), $user->passwordHash)) {
            $this->registerUserFailure($user->id, $maxUserAttempts, $lockMinutes, $now);
            $this->registerIpFailure($ip, $maxIpAttempts, $now);

            $security = $this->getOrCreateUserSecurity($user->id);

            if ($security->lockedUntil && Carbon::parse($security->lockedUntil)->isFuture()) {
                return response()->json(ApiResponse::error('Usuario bloqueado temporalmente', null, 423), 423);
            }

            if ($this->isIpBlocked($this->getOrCreateIpRecord($ip))) {
                return response()->json(ApiResponse::error('Dirección IP bloqueada permanentemente', null, 423), 423);
            }

            return response()->json(ApiResponse::error('Credenciales inválidas', null, 401), 401);
        }

        $token = app(JwtService::class)->issueToken((int) $user->id, (int) $user->roleId, $user->roleName);

        DB::table('userSecurity')
            ->where('userId', $user->id)
            ->update([
                'failedAttempts' => 0,
                'lastFailedAt' => null,
                'lockedUntil' => null,
                'lastKnownIp' => $ip,
                'sessionToken' => $token,
                'lastActivityAt' => $now,
                'lastLoginAt' => $now,
            ]);

        return response()->json(
            ApiResponse::success('Login exitoso', [
                'token' => $token,
                'tokenType' => 'Bearer',
                'expiresInMinutes' => (int) config('security.jwt_ttl_minutes'),
                'user' => [
                    'id' => $user->id,
                    'fullName' => $user->fullName,
                    'email' => $user->email,
                    'roleId' => $user->roleId,
                    'roleName' => $user->roleName,
                    'preferredLanguage' => $user->preferredLanguage,
                ],
            ]),
            200
        );
    }

    public function logout(Request $request)
    {
        $user = $request->attributes->get('authUser');

        if (!$user) {
            return response()->json(ApiResponse::error('Sesión no válida', null, 401), 401);
        }

        DB::table('userSecurity')
            ->where('userId', $user->id)
            ->update([
                'sessionToken' => null,
                'lastActivityAt' => Carbon::now(),
            ]);

        return response()->json(ApiResponse::success('Sesión cerrada'), 200);
    }

    private function getOrCreateUserSecurity(int $userId): object
    {
        $security = DB::table('userSecurity')->where('userId', $userId)->first();

        if ($security) {
            return $security;
        }

        DB::table('userSecurity')->insert([
            'userId' => $userId,
            'failedAttempts' => 0,
            'lastFailedAt' => null,
            'lockedUntil' => null,
            'lastKnownIp' => null,
            'sessionToken' => null,
            'lastActivityAt' => null,
            'lastLoginAt' => null,
        ]);

        return DB::table('userSecurity')->where('userId', $userId)->first();
    }

    private function getOrCreateIpRecord(string $ip): object
    {
        $record = DB::table('blockedIps')->where('ipAddress', $ip)->first();

        if ($record) {
            return $record;
        }

        DB::table('blockedIps')->insert([
            'ipAddress' => $ip,
            'failedAttempts' => 0,
            'isBlockedPermanently' => false,
            'blockedAt' => null,
            'releasedAt' => null,
            'adminNotes' => null,
        ]);

        return DB::table('blockedIps')->where('ipAddress', $ip)->first();
    }

    private function isIpBlocked(object $record): bool
    {
        return (bool) $record->isBlockedPermanently;
    }

    private function registerUserFailure(int $userId, int $maxAttempts, int $lockMinutes, Carbon $now): void
    {
        $security = $this->getOrCreateUserSecurity($userId);
        $nextAttempts = (int) $security->failedAttempts + 1;

        $update = [
            'failedAttempts' => $nextAttempts,
            'lastFailedAt' => $now,
        ];

        if ($nextAttempts >= $maxAttempts) {
            $update['lockedUntil'] = $now->copy()->addMinutes($lockMinutes);

            if (!$security->lockedUntil || Carbon::parse($security->lockedUntil)->isPast()) {
                $this->logUserBlock($userId, 'blocked', 'Bloqueo automático por intentos fallidos', null, null);
            }
        }

        DB::table('userSecurity')->where('userId', $userId)->update($update);
    }

    private function registerIpFailure(string $ip, int $maxAttempts, Carbon $now): void
    {
        $record = $this->getOrCreateIpRecord($ip);
        $nextAttempts = (int) $record->failedAttempts + 1;
        $update = [
            'failedAttempts' => $nextAttempts,
        ];

        if ($nextAttempts >= $maxAttempts) {
            $update['isBlockedPermanently'] = true;
            $update['blockedAt'] = $now;
            $update['releasedAt'] = null;

            if (!$record->isBlockedPermanently) {
                $this->logIpBlock($ip, 'blocked', 'Bloqueo automático por intentos fallidos', null, null);
            }
        }

        DB::table('blockedIps')->where('ipAddress', $ip)->update($update);
    }

    private function logUserBlock(int $userId, string $action, ?string $reason, ?string $ipAddress, ?int $adminUserId): void
    {
        DB::table('userBlockedHistory')->insert([
            'userId' => $userId,
            'action' => $action,
            'reason' => $reason,
            'ipAddress' => $ipAddress,
            'adminUserId' => $adminUserId,
            'createdAt' => Carbon::now(),
        ]);
    }

    private function logIpBlock(string $ipAddress, string $action, ?string $reason, ?int $userId, ?int $adminUserId): void
    {
        DB::table('ipBlockedHistory')->insert([
            'ipAddress' => $ipAddress,
            'action' => $action,
            'reason' => $reason,
            'userId' => $userId,
            'adminUserId' => $adminUserId,
            'createdAt' => Carbon::now(),
        ]);
    }
}
