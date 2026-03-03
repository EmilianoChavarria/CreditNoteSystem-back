<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Listar todos los customers
     */
    public function index()
    {
        $customers = Customer::with([
            'salesEngineer',
            'salesManager',
            'financeManager',
            'marketingManager',
            'customerServiceManager'
        ])->orderBy('id')->get();

        return response()->json(ApiResponse::success('Customers obtenidos exitosamente', $customers));
    }

    /**
     * Crear un nuevo customer
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customerNumber' => 'required|integer|unique:customers,customerNumber',
            'customerName' => 'required|string|max:255',
            'area' => 'nullable|in:AFTERMARKET,ORIGINAL EQUIPMENT',
            'salesEngineerId' => 'required|integer|exists:users,id',
            'salesManagerId' => 'required|integer|exists:users,id',
            'financeManagerId' => 'required|integer|exists:users,id',
            'marketingManagerId' => 'required|integer|exists:users,id',
            'customerServiceManagerId' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(
                ApiResponse::error('Error de validación', $validator->errors(), 422),
                422
            );
        }

        $data = $validator->validated();
        $data['isActive'] = $data['isActive'] ?? true;
        $data['createdAt'] = now();
        $data['updatedAt'] = now();

        $customer = Customer::create($data);

        return response()->json(
            ApiResponse::success('Customer creado exitosamente', $customer),
            201
        );
    }

    /**
     * Mostrar un customer específico
     */
    public function show(int $id)
    {
        $customer = Customer::find($id);

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
    public function searchByName(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:1|max:255',
        ], [
            'search.required' => 'El parámetro de búsqueda es requerido',
            'search.min' => 'El parámetro de búsqueda debe tener al menos 1 carácter',
        ]);

        if ($validator->fails()) {
            return response()->json(
                ApiResponse::error('Error de validación', $validator->errors(), 422),
                422
            );
        }

        $searchTerm = $request->input('search');

        $customers = Customer::with([
            'salesEngineer',
            'salesManager',
            'financeManager',
            'marketingManager',
            'customerServiceManager'
        ])
            ->where('customerName', 'LIKE', "%{$searchTerm}%")
            ->orderBy('customerName')
            ->get();

        return response()->json(ApiResponse::success(
            'Customers encontrados',
            [
                'search' => $searchTerm,
                'count' => $customers->count(),
                'customers' => $customers
            ],
            201
        ), 201);
    }

    /**
     * Actualizar un customer
     */
    public function update(Request $request, int $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(
                ApiResponse::error('Customer no encontrado', null, 404),
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'customerNumber' => [
                'sometimes',
                'required',
                'integer',
                Rule::unique('customers', 'customerNumber')->ignore($id)
            ],
            'customerName' => 'sometimes|required|string|max:255',
            'area' => 'nullable|in:AFTERMARKET,ORIGINAL EQUIPMENT',
            'salesEngineerId' => 'sometimes|required|integer|exists:users,id',
            'salesManagerId' => 'sometimes|required|integer|exists:users,id',
            'financeManagerId' => 'sometimes|required|integer|exists:users,id',
            'marketingManagerId' => 'sometimes|required|integer|exists:users,id',
            'customerServiceManagerId' => 'sometimes|required|integer|exists:users,id',
            'isActive' => 'nullable|boolean',
        ], [
            'customerNumber.required' => 'El número de cliente es requerido',
            'customerNumber.unique' => 'El número de cliente ya existe',
            'customerName.required' => 'El nombre del cliente es requerido',
            'area.in' => 'El área debe ser AFTERMARKET u ORIGINAL EQUIPMENT',
            'salesEngineerId.required' => 'El ingeniero de ventas es requerido',
            'salesEngineerId.exists' => 'El ingeniero de ventas no existe',
            'salesManagerId.required' => 'El gerente de ventas es requerido',
            'salesManagerId.exists' => 'El gerente de ventas no existe',
            'financeManagerId.required' => 'El gerente de finanzas es requerido',
            'financeManagerId.exists' => 'El gerente de finanzas no existe',
            'marketingManagerId.required' => 'El gerente de marketing es requerido',
            'marketingManagerId.exists' => 'El gerente de marketing no existe',
            'customerServiceManagerId.required' => 'El gerente de servicio al cliente es requerido',
            'customerServiceManagerId.exists' => 'El gerente de servicio al cliente no existe',
        ]);

        if ($validator->fails()) {
            return response()->json(
                ApiResponse::error('Error de validación', $validator->errors(), 422),
                422
            );
        }

        $data = $validator->validated();
        $data['updatedAt'] = now();

        $customer->update($data);

        return response()->json(
            ApiResponse::success('Customer actualizado exitosamente', $customer)
        );
    }

    /**
     * Eliminar un customer (soft delete)
     */
    public function destroy(int $id)
    {
        $customer = Customer::find($id);

        if (!$customer || $customer->deletedAt) {
            return response()->json(
                ApiResponse::error('Customer no encontrado', null, 404),
                404
            );
        }

        $customer->update(['deletedAt' => now()]);

        return response()->json(
            ApiResponse::success('Customer eliminado exitosamente', null)
        );
    }
}
