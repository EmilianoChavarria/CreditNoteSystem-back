<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Post(path: '/auth/login', operationId: 'authLogin', tags: ['Auth'], summary: 'Iniciar sesion',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['email', 'password'],
        properties: [
            new OA\Property(property: 'email',    type: 'string', format: 'email', example: 'user@example.com'),
            new OA\Property(property: 'password', type: 'string', example: 'secret123'),
        ]
    )),
    responses: [
        new OA\Response(response: 200, description: 'Login exitoso - retorna token JWT en cookie'),
        new OA\Response(response: 401, description: 'Credenciales invalidas'),
        new OA\Response(response: 423, description: 'Usuario o IP bloqueado'),
    ]
)]
#[OA\Post(path: '/auth/register', operationId: 'authRegister', tags: ['Auth'], summary: 'Registrar nuevo usuario',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['fullName', 'email', 'password', 'roleId'],
        properties: [
            new OA\Property(property: 'fullName',          type: 'string',  example: 'Juan Perez'),
            new OA\Property(property: 'email',             type: 'string',  format: 'email'),
            new OA\Property(property: 'password',          type: 'string',  example: 'P@ssw0rd!'),
            new OA\Property(property: 'roleId',            type: 'integer', example: 2),
            new OA\Property(property: 'supervisorId',      type: 'integer', nullable: true),
            new OA\Property(property: 'preferredLanguage', type: 'string',  enum: ['en', 'es'], nullable: true),
            new OA\Property(property: 'clientId',          type: 'string',  nullable: true),
        ]
    )),
    responses: [
        new OA\Response(response: 201, description: 'Usuario registrado'),
        new OA\Response(response: 422, description: 'Datos invalidos'),
    ]
)]
#[OA\Post(path: '/auth/logout', operationId: 'authLogout', tags: ['Auth'], summary: 'Cerrar sesion',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Sesion cerrada')]
)]
#[OA\Get(path: '/auth/verify', operationId: 'authVerify', tags: ['Auth'], summary: 'Verificar token JWT',
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Token valido'),
        new OA\Response(response: 401, description: 'Token invalido o expirado'),
    ]
)]
#[OA\Get(path: '/me', operationId: 'me', tags: ['Me'], summary: 'Datos del usuario autenticado',
    security: [['bearerAuth' => []]],
    responses: [new OA\Response(response: 200, description: 'Perfil del usuario')]
)]
#[OA\Get(path: '/admin/only', operationId: 'adminOnly', tags: ['Me'], summary: 'Endpoint exclusivo para administradores',
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Acceso permitido'),
        new OA\Response(response: 403, description: 'Sin permisos'),
    ]
)]
class AuthDocs
{
}
