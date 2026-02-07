<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class ApiResponse
{
    public static function make(
        int $codeStatus,
        bool $success,
        string $message,
        mixed $data = null,
        mixed $errors = null
    ): array {
        return [
            'codeStatus' => $codeStatus,
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }

    public static function success(string $message = 'OK', mixed $data = null, int $codeStatus = 200): array
    {
        return self::make($codeStatus, true, $message, $data, null);
    }

    public static function error(string $message = 'Error', mixed $errors = null, int $codeStatus = 400): array
    {
        return self::make($codeStatus, false, $message, null, $errors);
    }
}
