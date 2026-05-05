<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Notas Creditos API',
    version: '1.0.0',
    description: 'API para el sistema de notas credito, solicitudes, devoluciones y facturacion.',
    contact: new OA\Contact(email: 'aldrickemilianoch@gmail.com')
)]
#[OA\Server(url: '/api', description: 'API Principal')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'JWT')]
#[OA\Tag(name: 'Auth',                   description: 'Autenticacion de usuarios')]
#[OA\Tag(name: 'Me',                     description: 'Perfil del usuario autenticado')]
#[OA\Tag(name: 'Users',                  description: 'Gestion de usuarios')]
#[OA\Tag(name: 'UserAssignments',        description: 'Asignaciones de usuarios a lideres')]
#[OA\Tag(name: 'Roles',                  description: 'Gestion de roles')]
#[OA\Tag(name: 'Modules',               description: 'Gestion de modulos')]
#[OA\Tag(name: 'RolesPermission',        description: 'Permisos de modulos por rol')]
#[OA\Tag(name: 'Actions',               description: 'Catalogo de acciones')]
#[OA\Tag(name: 'Classifications',        description: 'Clasificaciones de solicitud')]
#[OA\Tag(name: 'RequestTypes',           description: 'Tipos de solicitud')]
#[OA\Tag(name: 'RequestTypePermissions', description: 'Permisos por tipo de solicitud')]
#[OA\Tag(name: 'Requests',              description: 'Ciclo de vida de solicitudes')]
#[OA\Tag(name: 'Workflows',             description: 'Flujos de trabajo')]
#[OA\Tag(name: 'WorkflowSteps',         description: 'Pasos de flujo de trabajo')]
#[OA\Tag(name: 'Batches',               description: 'Lotes de solicitudes')]
#[OA\Tag(name: 'Invoices',              description: 'Facturas XML')]
#[OA\Tag(name: 'ReturnOrders',          description: 'Ordenes de devolucion')]
#[OA\Tag(name: 'ReturnOrderRequests',   description: 'Vinculo devolucion-solicitud')]
#[OA\Tag(name: 'Customers',             description: 'Clientes')]
#[OA\Tag(name: 'Notifications',         description: 'Notificaciones del usuario')]
#[OA\Tag(name: 'Security',              description: 'Seguridad: IPs y usuarios bloqueados')]
#[OA\Tag(name: 'PasswordRequirements',  description: 'Requisitos de contrasena')]
#[OA\Tag(name: 'Dashboard',             description: 'Metricas y estadisticas')]
#[OA\Tag(name: 'ChargePolicies',        description: 'Politicas de cargo por dias transcurridos')]
class SwaggerInfo
{
}
