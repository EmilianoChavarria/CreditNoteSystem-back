<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NationalCustomers\UpdateNationalCustomerRequest;
use App\Http\Resources\NationalCustomerResource;
use App\Services\NationalCustomerService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NationalCustomerController extends Controller
{
    public function __construct(
        private readonly NationalCustomerService $nationalCustomerService
    ) {}

    public function index(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 15));
        $search  = trim((string) $request->query('search', ''));

        $nationalCustomers = $this->nationalCustomerService->getPaginated($perPage, $search);
        $nationalCustomers->setCollection(NationalCustomerResource::collection($nationalCustomers->getCollection())->collection);

        return response()->json(ApiResponse::success('Clientes nacionales', $nationalCustomers));
    }

    public function update(UpdateNationalCustomerRequest $request, string $customerNumber)
    {
        if (!$this->nationalCustomerService->existsInInvoices($customerNumber)) {
            throw ValidationException::withMessages([
                'customerNumber' => ["El customer number '{$customerNumber}' no existe en la base de datos de invoices."],
            ]);
        }

        $nationalCustomer = $this->nationalCustomerService->upsertByCustomerNumber(
            $customerNumber,
            $request->validated()
        );

        $isNew   = $nationalCustomer->wasRecentlyCreated;
        $message = $isNew ? 'Cliente nacional creado exitosamente' : 'Cliente nacional actualizado exitosamente';
        $status  = $isNew ? 201 : 200;

        return response()->json(
            ApiResponse::success($message, NationalCustomerResource::make($nationalCustomer), $status),
            $status
        );
    }
}
