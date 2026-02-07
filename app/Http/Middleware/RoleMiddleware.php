<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $roleName = $request->attributes->get('authRole');

        if (!$roleName || !in_array($roleName, $roles, true)) {
            return response()->json(ApiResponse::error('Acceso denegado por rol', null, 403), 403);
        }

        return $next($request);
    }
}
