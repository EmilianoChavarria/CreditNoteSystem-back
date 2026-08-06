<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NationalCustomers\BulkStoreNationalCustomersRequest;
use App\Http\Requests\NationalCustomers\StoreNationalCustomerRequest;
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

    /** Candidatos para dar de alta: clientes de la BD externa que aún no participan. */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        $candidates = $this->nationalCustomerService->searchCandidates($term);

        return response()->json(ApiResponse::success('Clientes disponibles', $candidates));
    }

    public function store(StoreNationalCustomerRequest $request)
    {
        $data           = $request->validated();
        $customerNumber = trim((string) $data['customerNumber']);
        unset($data['customerNumber']);

        $this->ensureExistsInInvoices($customerNumber);

        if ($this->nationalCustomerService->existsByCustomerNumber($customerNumber)) {
            throw ValidationException::withMessages([
                'customerNumber' => ["El cliente '{$customerNumber}' ya participa en forecast."],
            ]);
        }

        $nationalCustomer = $this->nationalCustomerService->create($customerNumber, array_filter(
            $data,
            fn ($value) => $value !== null
        ));

        return response()->json(
            ApiResponse::success('Cliente agregado a forecast', NationalCustomerResource::make($nationalCustomer), 201),
            201
        );
    }

    /** Alta masiva por arreglo de números de cliente. */
    public function bulkStore(BulkStoreNationalCustomersRequest $request)
    {
        $summary = $this->nationalCustomerService->addMany($request->validated()['customerNumbers']);

        return response()->json(ApiResponse::success('Alta masiva procesada', $summary));
    }

    public function update(UpdateNationalCustomerRequest $request, string $customerNumber)
    {
        $this->ensureExistsInInvoices($customerNumber);

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

    public function destroy(string $customerNumber)
    {
        if (!$this->nationalCustomerService->remove($customerNumber)) {
            throw ValidationException::withMessages([
                'customerNumber' => ["El cliente '{$customerNumber}' no participa en forecast."],
            ]);
        }

        return response()->json(ApiResponse::success('Cliente eliminado de forecast'));
    }

    private function ensureExistsInInvoices(string $customerNumber): void
    {
        if (!$this->nationalCustomerService->existsInInvoices($customerNumber)) {
            throw ValidationException::withMessages([
                'customerNumber' => ["El customer number '{$customerNumber}' no existe en la base de datos de invoices."],
            ]);
        }
    }
}
