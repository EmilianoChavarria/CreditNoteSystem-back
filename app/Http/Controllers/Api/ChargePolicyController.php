<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChargePolicies\StoreChargePolicyRequest;
use App\Http\Requests\ChargePolicies\SyncChargePoliciesRequest;
use App\Http\Requests\ChargePolicies\UpdateChargePolicyRequest;
use App\Http\Resources\ChargePolicyResource;
use App\Models\ChargePolicy;
use App\Services\ChargePolicyService;
use App\Support\ApiResponse;

class ChargePolicyController extends Controller
{
    public function __construct(
        private readonly ChargePolicyService $chargePolicyService
    ) {
    }

    public function index()
    {
        $policies = $this->chargePolicyService->getAll();

        return response()->json(ApiResponse::success('Políticas de cargo obtenidas', ChargePolicyResource::collection($policies)));
    }

    public function show(int $id)
    {
        $policy = ChargePolicy::find($id);

        if (!$policy) {
            return response()->json(ApiResponse::error('Política de cargo no encontrada', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Política de cargo obtenida', ChargePolicyResource::make($policy)));
    }

    public function store(StoreChargePolicyRequest $request)
    {
        $policy = $this->chargePolicyService->create($request->validated());

        return response()->json(
            ApiResponse::success('Política de cargo creada correctamente', ChargePolicyResource::make($policy), 201),
            201
        );
    }

    public function update(UpdateChargePolicyRequest $request, int $id)
    {
        $policy = ChargePolicy::find($id);

        if (!$policy) {
            return response()->json(ApiResponse::error('Política de cargo no encontrada', null, 404), 404);
        }

        $policy = $this->chargePolicyService->update($policy, $request->validated());

        return response()->json(ApiResponse::success('Política de cargo actualizada correctamente', ChargePolicyResource::make($policy)));
    }

    public function sync(SyncChargePoliciesRequest $request)
    {
        $policies = $this->chargePolicyService->sync($request->validated()['policies']);

        return response()->json(ApiResponse::success('Políticas de cargo guardadas correctamente', ChargePolicyResource::collection($policies)));
    }

    public function destroy(int $id)
    {
        $policy = ChargePolicy::find($id);

        if (!$policy) {
            return response()->json(ApiResponse::error('Política de cargo no encontrada', null, 404), 404);
        }

        $this->chargePolicyService->delete($policy);

        return response()->json(ApiResponse::success('Política de cargo eliminada correctamente'));
    }
}
