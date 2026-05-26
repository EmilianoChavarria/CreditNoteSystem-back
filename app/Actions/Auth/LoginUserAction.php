<?php

namespace App\Actions\Auth;

use App\Models\BlockedIp;
use App\Models\IpBlockedHistory;
use App\Models\User;
use App\Models\UserBlockedHistory;
use App\Models\UserSecurity;
use App\Services\JwtService;
use App\Services\LoginAttemptSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class LoginUserAction
{
    public function __construct(
        private readonly LoginAttemptSettingsService $loginAttemptSettingsService,
        private readonly JwtService $jwtService,
    ) {
    }

    public function execute(array $data, string $ip): array
    {
        $now = Carbon::now();
        $attemptSettings = $this->loginAttemptSettingsService->getSettings();
        $maxUserAttempts = (int) $attemptSettings->maxUserAttempts;
        $maxIpAttempts = (int) $attemptSettings->maxIpAttempts;
        $sessionTimeoutMinutes = (int) $attemptSettings->sessionTimeoutMinutes;
        $lockMinutes = (int) config('security.user_lock_minutes');

        $user = User::with('role')
            ->where('email', $data['email'])
            ->first();
        $security = $user ? $this->getOrCreateUserSecurity((int) $user->id) : null;
        $isAdmin = $user?->role?->roleName === 'ADMIN';
        $ipRecord = $this->getOrCreateIpRecord($ip);

        if ($this->isIpBlocked($ipRecord)) {
            if ($this->shouldRegisterUserFailure($user, $isAdmin, (string) $data['password'])) {
                $this->registerUserFailure((int) $user->id, $maxUserAttempts, $lockMinutes, $now, $ip);
            }

            return [
                'ok' => false,
                'status' => 423,
                'message' => 'Direccion IP bloqueada permanentemente',
            ];
        }

        if (!$user || !$user->isActive || $user->deletedAt) {
            $this->registerIpFailure($ip, $maxIpAttempts, $now);

            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Credenciales invalidas',
            ];
        }

        if (!$isAdmin && $security->lockedUntil && Carbon::parse($security->lockedUntil)->isFuture()) {
            $this->registerIpFailure($ip, $maxIpAttempts, $now);

            return [
                'ok' => false,
                'status' => 423,
                'message' => 'Usuario bloqueado temporalmente',
            ];
        }

        if (!Hash::check((string) $data['password'], (string) $user->passwordHash)) {
            $userWasBlocked = false;

            if (!$isAdmin) {
                $userWasBlocked = $this->registerUserFailure((int) $user->id, $maxUserAttempts, $lockMinutes, $now, $ip);
            }

            $this->registerIpFailure($ip, $maxIpAttempts, $now);

            return [
                'ok' => false,
                'status' => $userWasBlocked ? 423 : 401,
                'message' => $userWasBlocked ? 'Usuario bloqueado temporalmente' : 'Credenciales invalidas',
            ];
        }

        $roleName = $user->role?->roleName;
        $token = $this->jwtService->issueToken((int) $user->id, (int) $user->roleId, $roleName, $sessionTimeoutMinutes);

        $security->update([
            'failedAttempts' => 0,
            'lastFailedAt' => null,
            'lockedUntil' => null,
            'lastKnownIp' => $ip,
            'sessionToken' => $token,
            'lastActivityAt' => $now,
            'lastLoginAt' => $now,
        ]);

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Login exitoso',
            'token' => $token,
            'sessionTimeoutMinutes' => $sessionTimeoutMinutes,
            'user' => [
                'id' => $user->id,
                'fullName' => $user->fullName,
                'email' => $user->email,
                'roleId' => $user->roleId,
                'roleName' => $roleName,
                'preferredLanguage' => $user->preferredLanguage,
                'clientId' => $user->clientId,
                'mustChangePassword' => (bool) $user->mustChangePassword,
            ],
        ];
    }

    private function shouldRegisterUserFailure(?User $user, bool $isAdmin, string $password): bool
    {
        return $user !== null
            && (bool) $user->isActive
            && !$user->deletedAt
            && !$isAdmin
            && !Hash::check($password, (string) $user->passwordHash);
    }

    private function getOrCreateUserSecurity(int $userId): UserSecurity
    {
        $security = UserSecurity::where('userId', $userId)->first();

        if ($security) {
            return $security;
        }

        return UserSecurity::create([
            'userId' => $userId,
            'failedAttempts' => 0,
            'lastFailedAt' => null,
            'lockedUntil' => null,
            'lastKnownIp' => null,
            'sessionToken' => null,
            'lastActivityAt' => null,
            'lastLoginAt' => null,
        ]);
    }

    private function getOrCreateIpRecord(string $ip): BlockedIp
    {
        $record = BlockedIp::where('ipAddress', $ip)->first();

        if ($record) {
            return $record;
        }

        return BlockedIp::create([
            'ipAddress' => $ip,
            'failedAttempts' => 0,
            'isBlockedPermanently' => false,
            'blockedAt' => null,
            'releasedAt' => null,
        ]);
    }

    private function isIpBlocked(BlockedIp $record): bool
    {
        return (bool) $record->isBlockedPermanently;
    }

    private function registerUserFailure(int $userId, int $maxAttempts, int $lockMinutes, Carbon $now, string $ip): bool
    {
        $security = $this->getOrCreateUserSecurity($userId);
        $nextAttempts = (int) $security->failedAttempts + 1;
        $wasAlreadyLocked = $security->lockedUntil && Carbon::parse($security->lockedUntil)->isFuture();

        $update = [
            'failedAttempts' => $nextAttempts,
            'lastFailedAt' => $now,
        ];

        if ($nextAttempts >= $maxAttempts) {
            $update['lockedUntil'] = $now->copy()->addMinutes($lockMinutes);
        }

        UserSecurity::where('userId', $userId)->update($update);

        $isNowBlocked = isset($update['lockedUntil']);

        if ($isNowBlocked && !$wasAlreadyLocked) {
            UserBlockedHistory::create([
                'userId' => $userId,
                'action' => 'blocked',
                'reason' => "Maximo de {$maxAttempts} intentos fallidos alcanzado",
                'adminUserId' => null,
                'createdAt' => $now,
            ]);
        }

        return $isNowBlocked;
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
        }

        BlockedIp::where('ipAddress', $ip)->update($update);

        if (isset($update['isBlockedPermanently']) && !(bool) $record->isBlockedPermanently) {
            IpBlockedHistory::create([
                'ipAddress' => $ip,
                'action' => 'blocked',
                'reason' => "Maximo de {$maxAttempts} intentos fallidos alcanzado",
                'createdAt' => $now,
            ]);
        }
    }
}
