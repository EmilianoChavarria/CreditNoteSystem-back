<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Distributors\StoreDistributorForecastRequest;
use App\Http\Requests\Distributors\UpdateDistributorForecastMonthRequest;
use App\Models\Distributor;
use App\Services\DistributorForecastService;
use App\Services\ForecastApprovalService;
use App\Support\ApiResponse;

class DistributorForecastController extends Controller
{
    use ResolvesAuthenticatedUser;

    public function __construct(
        private readonly DistributorForecastService $forecastService,
        private readonly ForecastApprovalService $approvalService
    ) {
    }

    public function index(int $distributorId, int $year)
    {
        if (!Distributor::where('id', $distributorId)->exists()) {
            return response()->json(ApiResponse::error('Distribuidor no encontrado', null, 404), 404);
        }

        $rows = $this->forecastService->getByDistributor($distributorId, $year);

        return response()->json(ApiResponse::success('Forecast de distribuidor obtenido exitosamente', $rows));
    }

    public function indexBySalesEngineer(int $salesEngineerId, int $year)
    {
        $result = $this->forecastService->getBySalesEngineer($salesEngineerId, $year);

        return response()->json(ApiResponse::success('Distribuidores con forecast', $result));
    }

    public function store(StoreDistributorForecastRequest $request, int $distributorId)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->approvalService->isForecastAdmin($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para guardar el forecast de distribuidores', null, 403), 403);
        }

        if (!Distributor::where('id', $distributorId)->exists()) {
            return response()->json(ApiResponse::error('Distribuidor no encontrado', null, 404), 404);
        }

        $data  = $request->validated();
        $saved = $this->forecastService->upsertMonths($distributorId, $data['year'], $data['months']);

        return response()->json(ApiResponse::success('Forecast de distribuidor guardado exitosamente', $saved), 201);
    }

    public function updateMonth(UpdateDistributorForecastMonthRequest $request, int $distributorId, int $year, int $month)
    {
        $actor = $this->resolveAuthenticatedUser($request);

        if (!$actor) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        if (!$this->approvalService->isForecastAdmin($actor)) {
            return response()->json(ApiResponse::error('No tienes permisos para guardar el forecast de distribuidores', null, 403), 403);
        }

        if (!Distributor::where('id', $distributorId)->exists()) {
            return response()->json(ApiResponse::error('Distribuidor no encontrado', null, 404), 404);
        }

        $data = $request->validated();
        $row  = $this->forecastService->upsertMonth(
            $distributorId,
            $year,
            $month,
            isset($data['forecast']) ? (int) $data['forecast'] : null,
            isset($data['sales']) ? (int) $data['sales'] : null
        );

        return response()->json(ApiResponse::success('Forecast de distribuidor actualizado exitosamente', [
            'month'        => $row->month,
            'forecast'     => $row->forecast,
            'sales'        => $row->sales,
            'modification' => null,
        ]));
    }
}
