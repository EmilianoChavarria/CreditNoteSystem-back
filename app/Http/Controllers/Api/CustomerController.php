<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\SearchCustomerRequest;
use App\Http\Requests\Customers\StoreCustomerLocalRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerQueryService $customerQueryService
    ) {
    }

    /**
     * Listar todos los customers
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page');
        $search = trim((string) $request->query('search', ''));

        $customers = $this->customerQueryService->paginated(
            $perPage !== null ? (int) $perPage : null,
            $search
        );

        if ($customers === null) {
            return response()->json(ApiResponse::success('Customers obtenidos exitosamente', []));
        }

        return response()->json(ApiResponse::success('Customers obtenidos exitosamente', $customers));
    }

    /**
     * Crear un nuevo customer
     */
    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        return response()->json(
            ApiResponse::success('Customer creado exitosamente', CustomerResource::make($customer)),
            201
        );
    }

    public function storeInLocalTable(StoreCustomerLocalRequest $request)
    {
        $customer = Customer::create($request->validated());


        return response()->json(ApiResponse::success('Usuario creado correctamente', CustomerResource::make($customer), 201), 201);
    }

    /**
     * Mostrar un customer específico
     */
    public function show(int $id)
    {
        $customer = $this->customerQueryService->findById($id);

        if (!$customer) {
            return response()->json(
                ApiResponse::error('Customer no encontrado', null, 404),
                404
            );
        }

        return response()->json(ApiResponse::success('Customer obtenido exitosamente', $customer));
    }

    /**
     * Buscar customers por nombre (coincidencia parcial)
     */
    public function searchByName(SearchCustomerRequest $request)
    {
        $searchTerm = (string) $request->input('search');
        $result = $this->customerQueryService->searchByName($searchTerm);

        return response()->json(ApiResponse::success(
            'Customers encontrados',
            $result,
            201
        ), 201);
    }

    /**
     * Actualizar un customer
     */
    public function update(UpdateCustomerRequest $request, int $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(
                ApiResponse::error('Customer no encontrado', null, 404),
                404
            );
        }

        $customer->update($request->validated());

        return response()->json(
            ApiResponse::success('Customer actualizado exitosamente', CustomerResource::make($customer))
        );
    }

    /**
     * Eliminar un customer (soft delete)
     */
    public function destroy(int $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(
                ApiResponse::error('Customer no encontrado', null, 404),
                404
            );
        }

        $customer->delete();

        return response()->json(
            ApiResponse::success('Customer eliminado exitosamente', null)
        );
    }
}
