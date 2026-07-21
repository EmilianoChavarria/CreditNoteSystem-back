<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distributors\UpdateDistributorRequest;
use App\Http\Resources\DistributorResource;
use App\Services\DistributorService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class DistributorController extends Controller
{
    public function __construct(
        private readonly DistributorService $distributorService
    ) {}

    public function index(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 15));
        $search  = trim((string) $request->query('search', ''));

        $distributors = $this->distributorService->getPaginated($perPage, $search);
        $distributors->setCollection(DistributorResource::collection($distributors->getCollection())->collection);

        return response()->json(ApiResponse::success('Distribuidores', $distributors));
    }

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
