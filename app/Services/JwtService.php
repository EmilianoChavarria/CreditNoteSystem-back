<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class JwtService
{
    public function issueToken(int $userId, int $roleId, ?string $roleName = null): string
    {
        $now = Carbon::now()->timestamp;
        $ttlMinutes = (int) config('security.jwt_ttl_minutes');

        $payload = [
            'iss' => config('security.jwt_issuer'),
            'sub' => $userId,
            'roleId' => $roleId,
            'roleName' => $roleName,
            'iat' => $now,
            'exp' => Carbon::now()->addMinutes($ttlMinutes)->timestamp,
            'jti' => (string) Str::uuid(),
        ];

        return JWT::encode($payload, $this->getSecret(), 'HS256');
    }

    public function decodeToken(string $token): object
    {
        return JWT::decode($token, new Key($this->getSecret(), 'HS256'));
    }

    private function getSecret(): string
    {
        $secret = (string) config('security.jwt_secret');

        if (str_starts_with($secret, 'base64:')) {
            return base64_decode(substr($secret, 7));
        }

        return $secret;
    }
}
