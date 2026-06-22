<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distributors\UpdateDistributorRequest;
use App\Http\Resources\DistributorResource;
use App\Services\DistributorService;
use App\Support\ApiResponse;

class DistributorController extends Controller
{
    public function __construct(
        private readonly DistributorService $distributorService
    ) {}

    public function update(UpdateDistributorRequest $request, string $clientNumber)
    {
        $distributor = $this->distributorService->upsertByClientNumber(
            $clientNumber,
            $request->validated()
        );

        $isNew    = $distributor->wasRecentlyCreated;
        $message  = $isNew ? 'Distribuidor creado exitosamente' : 'Distribuidor actualizado exitosamente';
        $status   = $isNew ? 201 : 200;

        return response()->json(
            ApiResponse::success($message, DistributorResource::make($distributor), $status),
            $status
        );
    }
}
