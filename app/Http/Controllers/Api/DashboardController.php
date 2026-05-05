<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Request as RequestModel;
use App\Models\RequestTypePermission;
use App\Models\WorkflowRequestHistory;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getDays(Request $request)
    {
        [$inicio, $fin, $granularity] = $this->resolveDateRange($request);
        $requestTypeId = $this->resolveRequestTypeId($request->input('request_type_id'));
        $user = $request->attributes->get('authUser');

        if (!$user) {
            return response()->json(ApiResponse::error('Sesión no válida', null, 401), 401);
        }

        $series = $this->buildSeriesByRole($user, $inicio, $fin, $granularity, $requestTypeId);
        $totals = $this->calculateTotals($user, $inicio, $fin, $requestTypeId);

        return response()->json(ApiResponse::success('Conteo de Requests por periodo', [
            'from' => $inicio->toDateString(),
            'to' => $fin->toDateString(),
            'granularity' => $granularity,
            'requestTypeId' => $requestTypeId,
            'totals' => $totals,
            'series' => $series,
        ]));
    }

    private function buildSeriesByRole(object $user, Carbon $inicio, Carbon $fin, string $granularity, ?int $requestTypeId): array
    {
        $isAdmin = strtoupper((string) ($user->roleName ?? '')) === 'ADMIN';
        $roleId = (int) ($user->roleId ?? 0);
        $userId = (int) ($user->id ?? 0);

        if ($isAdmin) {
            return [
                [
                    'key' => 'created',
                    'label' => 'Solicitudes creadas',
                    'data' => $this->buildSeriesData(
                        RequestModel::query()
                            ->when($requestTypeId, fn ($query) => $query->where('requestTypeId', $requestTypeId))
                            ->whereBetween('createdAt', [$inicio, $fin]),
                        'createdAt',
                        $inicio,
                        $fin,
                        $granularity
                    ),
                ],
                [
                    'key' => 'approved',
                    'label' => 'Aprobaciones',
                    'data' => $this->buildSeriesData(
                        WorkflowRequestHistory::query()
                            ->join('requests', 'workflowrequesthistory.requestId', '=', 'requests.id')
                            ->when($requestTypeId, fn ($query) => $query->where('requests.requestTypeId', $requestTypeId))
                            ->whereBetween('workflowrequesthistory.createdAt', [$inicio, $fin])
                            ->where('workflowrequesthistory.actionType', 'approved'),
                        'workflowrequesthistory.createdAt',
                        $inicio,
                        $fin,
                        $granularity
                    ),
                ],
                [
                    'key' => 'rejected',
                    'label' => 'Rechazos',
                    'data' => $this->buildSeriesData(
                        WorkflowRequestHistory::query()
                            ->join('requests', 'workflowrequesthistory.requestId', '=', 'requests.id')
                            ->when($requestTypeId, fn ($query) => $query->where('requests.requestTypeId', $requestTypeId))
                            ->whereBetween('workflowrequesthistory.createdAt', [$inicio, $fin])
                            ->where('workflowrequesthistory.actionType', 'rejected'),
                        'workflowrequesthistory.createdAt',
                        $inicio,
                        $fin,
                        $granularity
                    ),
                ],
            ];
        }

        $series = [];
        $canCreate = $this->roleCanCreate($roleId);
        $canApprove = $this->roleCanApprove($roleId);
        $canReject = $this->roleCanReject($roleId);

        if ($canCreate) {
            $series[] = [
                'key' => 'created',
                'label' => 'Solicitudes creadas',
                'data' => $this->buildSeriesData(
                    RequestModel::query()
                        ->when($requestTypeId, fn ($query) => $query->where('requestTypeId', $requestTypeId))
                        ->whereBetween('createdAt', [$inicio, $fin])
                        ->where('userId', $userId),
                    'createdAt',
                    $inicio,
                    $fin,
                    $granularity
                ),
            ];
        }

        if ($canApprove) {
            $series[] = [
                'key' => 'approved',
                'label' => 'Aprobaciones',
                'data' => $this->buildSeriesData(
                    WorkflowRequestHistory::query()
                        ->join('requests', 'workflowrequesthistory.requestId', '=', 'requests.id')
                        ->when($requestTypeId, fn ($query) => $query->where('requests.requestTypeId', $requestTypeId))
                        ->whereBetween('workflowrequesthistory.createdAt', [$inicio, $fin])
                        ->where('workflowrequesthistory.actionUserId', $userId)
                        ->where('workflowrequesthistory.actionType', 'approved'),
                    'workflowrequesthistory.createdAt',
                    $inicio,
                    $fin,
                    $granularity
                ),
            ];
        }

        if ($canReject) {
            $series[] = [
                'key' => 'rejected',
                'label' => 'Rechazos',
                'data' => $this->buildSeriesData(
                    WorkflowRequestHistory::query()
                        ->join('requests', 'workflowrequesthistory.requestId', '=', 'requests.id')
                        ->when($requestTypeId, fn ($query) => $query->where('requests.requestTypeId', $requestTypeId))
                        ->whereBetween('workflowrequesthistory.createdAt', [$inicio, $fin])
                        ->where('workflowrequesthistory.actionUserId', $userId)
                        ->where('workflowrequesthistory.actionType', 'rejected'),
                    'workflowrequesthistory.createdAt',
                    $inicio,
                    $fin,
                    $granularity
                ),
            ];
        }

        if (!empty($series)) {
            return $series;
        }

        return [
            [
                'key' => 'created',
                'label' => 'Solicitudes creadas',
                'data' => $this->buildSeriesData(
                    RequestModel::query()->whereRaw('1 = 0'),
                    'createdAt',
                    $inicio,
                    $fin,
                    $granularity
                ),
            ],
        ];
    }

    private function resolveDateRange(Request $request): array
    {
        $daysInput = $request->input('days');
        $hasPresetDays = is_numeric($daysInput) && (int) $daysInput > 0;
        $granularity = 'day';
        $fromInput = $request->input('from') ?? $request->input('startDate');
        $toInput = $request->input('to') ?? $request->input('endDate');

        if ($hasPresetDays) {
            $days = (int) $daysInput;

            // Rango inclusivo: days=30 debe devolver exactamente 30 periodos diarios.
            $inicio = Carbon::now()->subDays($days - 1)->startOfDay();
            $fin = Carbon::now()->endOfDay();
            $granularity = $days > 30 ? 'month' : 'day';
        } elseif ($fromInput && $toInput) {
            $inicio = Carbon::parse($fromInput)->startOfDay();
            $fin = Carbon::parse($toInput)->endOfDay();
        } else {
            $days = 30;

            // Valor por defecto: ultimos 30 dias (incluyendo hoy).
            $inicio = Carbon::now()->subDays($days - 1)->startOfDay();
            $fin = Carbon::now()->endOfDay();
        }

        if ($inicio->gt($fin)) {
            [$inicio, $fin] = [$fin->copy()->startOfDay(), $inicio->copy()->endOfDay()];
        }

        if (!$hasPresetDays) {
            $totalDays = $inicio->diffInDays($fin) + 1;
            $granularity = $totalDays > 30 ? 'month' : 'day';
        }

        return [$inicio, $fin, $granularity];
    }

    private function resolveRequestTypeId(mixed $requestTypeId): ?int
    {
        if (is_numeric($requestTypeId) && (int) $requestTypeId > 0) {
            return (int) $requestTypeId;
        }

        return null;
    }

    private function buildSeriesData($query, string $dateColumn, Carbon $inicio, Carbon $fin, string $granularity): array
    {
        if ($granularity === 'month') {
            $conteos = $query
                ->selectRaw("DATE_FORMAT({$dateColumn}, '%Y-%m') as periodo, count(*) as total")
                ->groupBy('periodo')
                ->pluck('total', 'periodo');

            $periodo = CarbonPeriod::create($inicio->copy()->startOfMonth(), '1 month', $fin->copy()->startOfMonth());
        } else {
            $conteos = $query
                ->selectRaw("DATE({$dateColumn}) as periodo, count(*) as total")
                ->groupBy('periodo')
                ->pluck('total', 'periodo');

            $periodo = CarbonPeriod::create($inicio, $fin);
        }

        $resultado = [];

        foreach ($periodo as $fecha) {
            $periodoClave = $granularity === 'month'
                ? $fecha->format('Y-m')
                : $fecha->format('Y-m-d');

            $resultado[] = [
                'dia' => $periodoClave,
                'periodo' => $periodoClave,
                'cantidad' => (int) $conteos->get($periodoClave, 0),
            ];
        }

        return $resultado;
    }

    private function buildDailySeries($conteos, Carbon $inicio, Carbon $fin): array
    {
        $periodo = CarbonPeriod::create($inicio, $fin);

        $resultado = [];
        foreach ($periodo as $fecha) {
            $fechaString = $fecha->format('Y-m-d');

            $resultado[] = [
                'dia' => $fechaString,
                'cantidad' => (int) $conteos->get($fechaString, 0),
            ];
        }

        return $resultado;
    }

    private function calculateTotals(object $user, Carbon $inicio, Carbon $fin, ?int $requestTypeId): array
    {
        $isAdmin = strtoupper((string) ($user->roleName ?? '')) === 'ADMIN';
        $roleId = (int) ($user->roleId ?? 0);
        $userId = (int) ($user->id ?? 0);

        $totals = [];

        if ($isAdmin) {
            $totals['created'] = RequestModel::query()
                ->when($requestTypeId, fn ($query) => $query->where('requestTypeId', $requestTypeId))
                ->whereBetween('createdAt', [$inicio, $fin])
                ->count();

            $totals['approved'] = WorkflowRequestHistory::query()
                ->join('requests', 'workflowrequesthistory.requestId', '=', 'requests.id')
                ->when($requestTypeId, fn ($query) => $query->where('requests.requestTypeId', $requestTypeId))
                ->whereBetween('workflowrequesthistory.createdAt', [$inicio, $fin])
                ->where('workflowrequesthistory.actionType', 'approved')
                ->count();

            $totals['declined'] = WorkflowRequestHistory::query()
                ->join('requests', 'workflowrequesthistory.requestId', '=', 'requests.id')
                ->when($requestTypeId, fn ($query) => $query->where('requests.requestTypeId', $requestTypeId))
                ->whereBetween('workflowrequesthistory.createdAt', [$inicio, $fin])
                ->where('workflowrequesthistory.actionType', 'rejected')
                ->count();
        } else {
            $canCreate = $this->roleCanCreate($roleId);
            $canApprove = $this->roleCanApprove($roleId);
            $canReject = $this->roleCanReject($roleId);

            if ($canCreate) {
                $totals['created'] = RequestModel::query()
                    ->when($requestTypeId, fn ($query) => $query->where('requestTypeId', $requestTypeId))
                    ->whereBetween('createdAt', [$inicio, $fin])
                    ->where('userId', $userId)
                    ->count();
            }

            if ($canApprove) {
                $totals['approved'] = WorkflowRequestHistory::query()
                    ->join('requests', 'workflowrequesthistory.requestId', '=', 'requests.id')
                    ->when($requestTypeId, fn ($query) => $query->where('requests.requestTypeId', $requestTypeId))
                    ->whereBetween('workflowrequesthistory.createdAt', [$inicio, $fin])
                    ->where('workflowrequesthistory.actionUserId', $userId)
                    ->where('workflowrequesthistory.actionType', 'approved')
                    ->count();
            }

            if ($canReject) {
                $totals['declined'] = WorkflowRequestHistory::query()
                    ->join('requests', 'workflowrequesthistory.requestId', '=', 'requests.id')
                    ->when($requestTypeId, fn ($query) => $query->where('requests.requestTypeId', $requestTypeId))
                    ->whereBetween('workflowrequesthistory.createdAt', [$inicio, $fin])
                    ->where('workflowrequesthistory.actionUserId', $userId)
                    ->where('workflowrequesthistory.actionType', 'rejected')
                    ->count();
            }

            if (empty($totals)) {
                return ['created' => 0];
            }
        }

        return $totals;
    }

    private function roleCanCreate(int $roleId): bool
    {
        return $this->roleHasRequestAction($roleId, ['create', 'crear']);
    }

    private function roleCanApprove(int $roleId): bool
    {
        return $this->roleHasRequestAction($roleId, ['approve', 'aprobar']);
    }

    private function roleCanReject(int $roleId): bool
    {
        return $this->roleHasRequestAction($roleId, ['reject', 'decline', 'rechazar']);
    }

    private function roleHasRequestAction(int $roleId, array $keywords): bool
    {
        if ($roleId <= 0) {
            return false;
        }

        return RequestTypePermission::query()
            ->join('actions', 'requesttypepermissions.action_id', '=', 'actions.id')
            ->where('requesttypepermissions.role_id', $roleId)
            ->where('requesttypepermissions.is_allowed', true)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $needle = '%' . strtolower($keyword) . '%';
                    $query->orWhereRaw('LOWER(actions.slug) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(actions.name) LIKE ?', [$needle]);
                }
            })
            ->exists();
    }
}
