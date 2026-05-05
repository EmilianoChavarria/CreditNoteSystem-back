<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\UserSecurity;
use App\Services\JwtService;
use App\Services\LoginAttemptSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserAction $registerUserAction,
        private readonly LoginUserAction $loginUserAction,
        private readonly LoginAttemptSettingsService $loginAttemptSettingsService
    )
    {
    }

    public function register(RegisterRequest $request)
    {
        try {
            $result = $this->registerUserAction->execute($request->validated());

            return response()->json(ApiResponse::success('Usuario registrado', [
                'id' => $result['id'],
                'fullName' => $result['fullName'],
                'email' => $result['email'],
                'roleId' => $result['roleId'],
            ], 201), 201);
        } catch (ValidationException $e) {
            return response()->json(ApiResponse::error('Datos inválidos', $e->errors(), 422), 422);
        }
    }

    public function login(LoginRequest $request)
    {
        $result = $this->loginUserAction->execute($request->validated(), $request->ip());

        if (!$result['ok']) {
            return response()->json(ApiResponse::error($result['message'], null, $result['status']), $result['status']);
        }

        $cookie = $this->makeAccessTokenCookie($result['token'], (int) $result['sessionTimeoutMinutes']);

        return response()->json(
            ApiResponse::success('Login exitoso', [
                'user' => $result['user'],
                'sessionTimeoutMinutes' => $result['sessionTimeoutMinutes'],
            ]),
            200
        )->withCookie($cookie);
    }

    public function logout(Request $request)
    {
        $user = $request->attributes->get('authUser');

        if (!$user) {
            return response()->json(ApiResponse::error('Sesión no válida', null, 401), 401);
        }

        // 1. Limpiamos el token en la base de datos
        UserSecurity::where('userId', $user->id)
            ->update([
                'sessionToken' => null,
                'lastActivityAt' => Carbon::now(),
            ]);

        // 2. Preparamos la respuesta de éxito
        $response = response()->json(ApiResponse::success('Sesión cerrada'), 200);

        // 3. Invalidamos la cookie en el navegador
        // withoutCookie crea una cookie con el mismo nombre pero ya expirada
        return $response->withoutCookie('access_token');
    }

    public function verify(Request $request)
    {
        $user = $request->attributes->get('authUser');
        $tokenValid = (bool) $request->attributes->get('authTokenValid', false);

        if ($user && $tokenValid) {
            $attemptSettings = $this->loginAttemptSettingsService->getSettings();
            $sessionTimeoutMinutes = (int) $attemptSettings->sessionTimeoutMinutes;
            $minutes = $sessionTimeoutMinutes;
            $token = app(JwtService::class)->issueToken((int) $user->id, (int) $user->roleId, $user->roleName, $sessionTimeoutMinutes);

            UserSecurity::where('userId', $user->id)->update([
                'sessionToken' => $token,
                'lastActivityAt' => Carbon::now(),
                'lastKnownIp' => $request->ip(),
            ]);

            return response()->json(
                ApiResponse::success('Token renovado', [
                    'user' => [
                        'id' => $user->id,
                        'fullName' => $user->fullName,
                        'email' => $user->email,
                        'roleId' => $user->roleId,
                        'roleName' => $user->roleName,
                        'preferredLanguage' => $user->preferredLanguage,
                        'clientId' => $user->clientId,
                        'mustChangePassword' => (bool) $user->mustChangePassword,
                    ],
                    'isAuthenticated' => true,
                    'sessionTimeoutMinutes' => $sessionTimeoutMinutes,
                ], 200),
                200
            )->withCookie($this->makeAccessTokenCookie($token, $minutes));
        }

        $this->clearSessionFromToken($request->attributes->get('authToken'));

        return response()->json(
            ApiResponse::error('Sesión no válida', null, 401),
            401
        )->withoutCookie('access_token');
    }

    private function makeAccessTokenCookie(string $token, int $minutes)
    {
        return cookie(
            'access_token',
            $token,
            $minutes,
            '/',
            null,
            config('app.env') === 'production',
            true,
            false,
            'Lax'
        );
    }

    private function clearSessionFromToken(?string $token): void
    {
        if (!$token) {
            return;
        }

        UserSecurity::where('sessionToken', $token)->update([
            'sessionToken' => null,
            'lastActivityAt' => Carbon::now(),
        ]);
    }
}
