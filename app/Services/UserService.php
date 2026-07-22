<?php

namespace App\Services;

use App\Mail\UserRegisteredMail;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserService
{
    public function __construct(
        private readonly UserClientService $userClientService,
        private readonly EmailSenderService $emailSender,
    ) {
    }

    public function getUsersByManagerRole(): Collection
    {
        return $this->getUsersBySalesAndManagerRoles();
    }

    public function getUsersByRequesterRole(): Collection
    {
        return User::with('role')
            ->where('isActive', '1')
            ->whereNull('deletedAt')
            ->whereHas('role', fn($q) => $q->whereIn('roleName', ['REQUESTER', 'REQUESTER / PROCESSOR']))
            ->orderBy('fullName')
            ->get();
    }

    public function getUsersBySalesAndManagerRoles(): Collection
    {
        return User::with('role')
            ->where('isActive', '1')
            ->whereHas('role', fn($q) => $q->whereRaw('UPPER(roleName) LIKE ?', ['%MANAGER%']))
            ->orderBy('fullName')
            ->get();
    }

    public function getSalesManagers(): Collection
    {
        return User::with('role')
            ->where('isActive', '1')
            ->whereHas('role', fn($q) => $q->where('roleName', 'SALES ENGINEER / MANAGER'))
            ->orderBy('fullName')
            ->get();
    }

    public function getSalesEngineers(): Collection
    {
        return User::with('role')
            ->where('isActive', '1')
            ->whereHas('role', fn($q) => $q->where('roleName', 'SALES ENGINEER'))
            ->orderBy('fullName')
            ->get();
    }

    public function getAllActive(): Collection
    {
        return User::with('role')
            ->where('isActive', '1')
            ->whereHas('role', fn($q) => $q->where('roleName', '!=', 'SUPERADMIN'))
            ->get();
    }

    public function getPaginated(int $perPage, string $search, string $roleName): LengthAwarePaginator
    {
        $shouldFilterByRole = $roleName !== '' && strtolower($roleName) !== 'all';

        return User::with(['role', 'supervisor'])
            ->where('isActive', '1')
            ->whereHas('role', fn($q) => $q->where('roleName', '!=', 'SUPERADMIN'))
            ->when($shouldFilterByRole, fn($q) => $q->whereHas('role', fn($rq) => $rq->where('roleName', $roleName)))
            ->when($search !== '', fn($q) => $q->where(fn($sq) =>
                $sq->where('fullName', 'like', "%{$search}%")
                   ->orWhere('email', 'like', "%{$search}%")
                   ->orWhereHas('role', fn($rq) => $rq->where('roleName', 'like', "%{$search}%"))
            ))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getUserWithClient(int $id): ?array
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            return null;
        }

        $data = ['user' => $user];
        $clientData = $this->userClientService->findClientSummaryForUser($user);
        if ($clientData !== null) {
            $data['client'] = $clientData;
        }

        return $data;
    }

    public function hasRelatedRecords(User $user): bool
    {
        return $user->subordinates()->exists()
            || $user->requests()->exists()
            || $user->leaderAssignments()->exists()
            || $this->userClientService->isUserAssignedToAnyClient($user->id);
    }

    public function deactivate(User $user): void
    {
        $now = now();

        $user->fill([
            'passwordHash' => '',
            'isActive'     => false,
            'deletedAt'    => $now,
        ])->save();

        $security = $user->security;
        if ($security) {
            $security->update([
                'sessionToken'    => null,
                'lastActivityAt'  => $now,
            ]);
        }
    }

    public function resendWelcomeEmail(User $user, bool $testOnly, string $locale): void
    {
        $locale = $this->normalizeMailLocale($locale);
        $tempPassword = $this->generateTempPassword();

        if (!$testOnly) {
            $user->passwordHash = Hash::make($tempPassword);
            $user->save();
        }

        $this->emailSender->send(
            new UserRegisteredMail((string) $user->fullName, (string) $user->email, $tempPassword, $locale),
            (string) $user->email
        );
    }

    public function canManagePasswords(User $user): bool
    {
        $roleName = mb_strtoupper(trim((string) optional($user->role)->roleName));

        return str_contains($roleName, 'ADMIN') || str_contains($roleName, 'MANAGER');
    }

    public function isSuperAdmin(User $user): bool
    {
        return mb_strtoupper(trim((string) optional($user->role)->roleName)) === 'SUPERADMIN';
    }

    /**
     * Reinicia la contraseña de todos los usuarios activos (excepto SUPERADMIN) a la
     * contraseña default de carga masiva, obliga a cambiarla en el próximo login,
     * y envía las nuevas credenciales por correo a cada usuario.
     *
     * @return array{resetCount: int, emailedCount: int, failedEmails: array<int, string>}
     */
    public function resetAllPasswordsToDefault(): array
    {
        $defaultPassword = (string) config('bulk_upload.users.default_password', 'ChangeMe123!');
        $hashedPassword  = Hash::make($defaultPassword);

        $users = DB::transaction(function () use ($hashedPassword) {
            $users = User::with('role')
                ->where('isActive', '1')
                ->whereNull('deletedAt')
                ->whereHas('role', fn($q) => $q->where('roleName', '!=', 'SUPERADMIN'))
                ->get();

            foreach ($users as $user) {
                $user->passwordHash = $hashedPassword;
                $user->mustChangePassword = true;
                $user->save();
            }

            return $users;
        });

        $emailedCount = 0;
        $failedEmails = [];

        foreach ($users as $user) {
            if (empty($user->email)) {
                continue;
            }

            try {
                $this->emailSender->send(
                    new UserRegisteredMail(
                        (string) $user->fullName,
                        (string) $user->email,
                        $defaultPassword,
                        $this->normalizeMailLocale((string) ($user->preferredLanguage ?? 'es'))
                    ),
                    (string) $user->email
                );
                $emailedCount++;
            } catch (Throwable $e) {
                $failedEmails[] = (string) $user->email;
                Log::warning('[UserService] resetAllPasswordsToDefault: fallo al enviar credenciales', [
                    'userId' => $user->id,
                    'email'  => $user->email,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        return [
            'resetCount'   => $users->count(),
            'emailedCount' => $emailedCount,
            'failedEmails' => $failedEmails,
        ];
    }

    /**
     * Reinicia la contraseña de un único usuario a la contraseña default de carga masiva,
     * obliga a cambiarla en el próximo login, y le envía las nuevas credenciales por correo.
     *
     * @return array{resetCount: int, emailedCount: int, failedEmails: array<int, string>}
     */
    public function resetPasswordToDefault(User $user): array
    {
        $defaultPassword = (string) config('bulk_upload.users.default_password', 'ChangeMe123!');
        $hashedPassword  = Hash::make($defaultPassword);

        $user->passwordHash = $hashedPassword;
        $user->mustChangePassword = true;
        $user->save();

        $emailedCount = 0;
        $failedEmails = [];

        if (!empty($user->email)) {
            try {
                $this->emailSender->send(
                    new UserRegisteredMail(
                        (string) $user->fullName,
                        (string) $user->email,
                        $defaultPassword,
                        $this->normalizeMailLocale((string) ($user->preferredLanguage ?? 'es'))
                    ),
                    (string) $user->email
                );
                $emailedCount++;
            } catch (Throwable $e) {
                $failedEmails[] = (string) $user->email;
                Log::warning('[UserService] resetPasswordToDefault: fallo al enviar credenciales', [
                    'userId' => $user->id,
                    'email'  => $user->email,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        return [
            'resetCount'   => 1,
            'emailedCount' => $emailedCount,
            'failedEmails' => $failedEmails,
        ];
    }

    private function normalizeMailLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return in_array($locale, ['en', 'es'], true) ? $locale : 'es';
    }

    private function generateTempPassword(): string
    {
        $upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower   = 'abcdefghijklmnopqrstuvwxyz';
        $digits  = '0123456789';
        $special = '@#$!%';

        $password  = $upper[random_int(0, strlen($upper) - 1)];
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $all = $upper . $lower . $digits . $special;
        for ($i = 0; $i < 6; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }
}
