<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Listar todos los customers
     */
    public function index(Request $request)
    {
        $perPage = $request->query('perPage');

        $customerTable = (new Customer())->getTable();
        $clientTable = 'clientes_tme700618rc7';

        $clientColumns = Schema::hasTable($clientTable)
            ? Schema::getColumnListing($clientTable)
            : [];

        $canReadClients = in_array('idCliente', $clientColumns, true);

        if (!$canReadClients) {
            return response()->json(ApiResponse::success('Customers obtenidos exitosamente', []));
        }

        $selectColumns = [
            'c.idCustomer',
            'c.idClient',
            'c.salesEngineerId',
            'c.salesManagerId',
            'c.financeManagerId',
            'c.marketingManagerId',
            'c.customerServiceManagerId',
            'c.area',
            'se.id as salesEngineer_id',
            'se.fullName as salesEngineer_name',
            'sm.id as salesManager_id',
            'sm.fullName as salesManager_name',
            'fm.id as financeManager_id',
            'fm.fullName as financeManager_name',
            'mm.id as marketingManager_id',
            'mm.fullName as marketingManager_name',
            'csm.id as customerServiceManager_id',
            'csm.fullName as customerServiceManager_name',
        ];

        foreach ($clientColumns as $column) {
            $selectColumns[] = 'cl.' . $column . ' as client_' . $column;
        }

        $query = DB::table($clientTable . ' as cl')
            ->leftJoin($customerTable . ' as c', 'c.idClient', '=', 'cl.idCliente')
            ->leftJoin('users as se', 'c.salesEngineerId', '=', 'se.id')
            ->leftJoin('users as sm', 'c.salesManagerId', '=', 'sm.id')
            ->leftJoin('users as fm', 'c.financeManagerId', '=', 'fm.id')
            ->leftJoin('users as mm', 'c.marketingManagerId', '=', 'mm.id')
            ->leftJoin('users as csm', 'c.customerServiceManagerId', '=', 'csm.id')
            ->orderBy('cl.idCliente')
            ->select($selectColumns);

        $customers = $query->paginate();

        $customers->through(function ($row) use ($clientColumns) {
            $clientData = [];

            foreach ($clientColumns as $column) {
                $clientData[$column] = $row->{'client_' . $column} ?? null;
            }

            $customerData = null;

            if ($row->idCustomer !== null) {
                $customerData = [
                    'idCustomer' => $row->idCustomer,
                    'idClient' => $row->idClient,
                    'area' => $row->area,
                    'salesEngineerId' => $row->salesEngineerId,
                    'salesManagerId' => $row->salesManagerId,
                    'financeManagerId' => $row->financeManagerId,
                    'marketingManagerId' => $row->marketingManagerId,
                    'customerServiceManagerId' => $row->customerServiceManagerId,
                    'salesEngineer' => [
                        'id' => $row->salesEngineer_id,
                        'fullName' => $row->salesEngineer_name,
                    ],
                    'salesManager' => [
                        'id' => $row->salesManager_id,
                        'fullName' => $row->salesManager_name,
                    ],
                    'financeManager' => [
                        'id' => $row->financeManager_id,
                        'fullName' => $row->financeManager_name,
                    ],
                    'marketingManager' => [
                        'id' => $row->marketingManager_id,
                        'fullName' => $row->marketingManager_name,
                    ],
                    'customerServiceManager' => [
                        'id' => $row->customerServiceManager_id,
                        'fullName' => $row->customerServiceManager_name,
                    ],
                ];
            }

            return array_merge($clientData, ['customer' => $customerData]);
        });

        return response()->json(ApiResponse::success('Customers obtenidos exitosamente', $customers));
    }

    /**
     * Crear un nuevo customer
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idClient' => 'required|integer|unique:customers,idClient',
            'area' => 'required|in:sales,aftermarket',
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

        $customer = Customer::create($data);

        return response()->json(
            ApiResponse::success('Customer creado exitosamente', $customer),
            201
        );
    }

    public function storeInLocalTable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idClient' => ['required', 'string', 'max:255'],
            'salesEngineerId' => ['required', 'integer'],
            'salesManagerId' => ['required', 'integer'],
            'financeManagerId' => ['required', 'integer'],
            'marketingManagerId' => ['required', 'integer'],
            'customerServiceManagerId' => ['required', 'integer'],
            'area' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $data = $validator->validated();

        $customer = Customer::create($data);


        return response()->json(ApiResponse::success('Usuario creado correctamente', $customer, 201), 201);
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

        $customerTable = (new Customer())->getTable();
        $clientTable = 'clientes_tme700618rc7';
        $clientColumns = Schema::hasTable($clientTable)
            ? Schema::getColumnListing($clientTable)
            : [];

        $canReadClients = in_array('idCliente', $clientColumns, true)
            && in_array('razonSocial', $clientColumns, true);

        if (!$canReadClients) {
            return response()->json(ApiResponse::success(
                'Customers encontrados',
                [
                    'search' => $searchTerm,
                    'count' => 0,
                    'customers' => [],
                ],
                201
            ), 201);
        }

        $selectColumns = [
            'c.idCustomer',
            'c.idClient',
            'c.salesEngineerId',
            'c.salesManagerId',
            'c.financeManagerId',
            'c.marketingManagerId',
            'c.customerServiceManagerId',
            'c.area',
            'se.id as salesEngineer_id',
            'se.fullName as salesEngineer_name',
            'sm.id as salesManager_id',
            'sm.fullName as salesManager_name',
            'fm.id as financeManager_id',
            'fm.fullName as financeManager_name',
            'mm.id as marketingManager_id',
            'mm.fullName as marketingManager_name',
            'csm.id as customerServiceManager_id',
            'csm.fullName as customerServiceManager_name',
        ];

        foreach ($clientColumns as $column) {
            $selectColumns[] = 'cl.' . $column . ' as client_' . $column;
        }

        $customers = DB::table($clientTable . ' as cl')
            ->leftJoin($customerTable . ' as c', 'c.idClient', '=', 'cl.idCliente')
            ->leftJoin('users as se', 'c.salesEngineerId', '=', 'se.id')
            ->leftJoin('users as sm', 'c.salesManagerId', '=', 'sm.id')
            ->leftJoin('users as fm', 'c.financeManagerId', '=', 'fm.id')
            ->leftJoin('users as mm', 'c.marketingManagerId', '=', 'mm.id')
            ->leftJoin('users as csm', 'c.customerServiceManagerId', '=', 'csm.id')
            ->whereNotNull('c.idCustomer')
            ->where('cl.razonSocial', 'LIKE', '%' . $searchTerm . '%')
            ->orderBy('cl.idCliente')
            ->select($selectColumns)
            ->get()
            ->map(function ($row) use ($clientColumns) {
                $clientData = [];

                foreach ($clientColumns as $column) {
                    $clientData[$column] = $row->{'client_' . $column} ?? null;
                }

                $customerData = [
                    'idCustomer' => $row->idCustomer,
                    'idClient' => $row->idClient,
                    'area' => $row->area,
                    'salesEngineerId' => $row->salesEngineerId,
                    'salesManagerId' => $row->salesManagerId,
                    'financeManagerId' => $row->financeManagerId,
                    'marketingManagerId' => $row->marketingManagerId,
                    'customerServiceManagerId' => $row->customerServiceManagerId,
                    'salesEngineer' => [
                        'id' => $row->salesEngineer_id,
                        'fullName' => $row->salesEngineer_name,
                    ],
                    'salesManager' => [
                        'id' => $row->salesManager_id,
                        'fullName' => $row->salesManager_name,
                    ],
                    'financeManager' => [
                        'id' => $row->financeManager_id,
                        'fullName' => $row->financeManager_name,
                    ],
                    'marketingManager' => [
                        'id' => $row->marketingManager_id,
                        'fullName' => $row->marketingManager_name,
                    ],
                    'customerServiceManager' => [
                        'id' => $row->customerServiceManager_id,
                        'fullName' => $row->customerServiceManager_name,
                    ],
                ];

                return array_merge($clientData, ['customer' => $customerData]);
            })
            ->values();

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
            'idClient' => [
                'sometimes',
                'required',
                'integer',
                Rule::unique('customers', 'idClient')->ignore($id, 'idCustomer')
            ],
            'area' => 'sometimes|required|in:sales,aftermarket',
            'salesEngineerId' => 'sometimes|required|integer|exists:users,id',
            'salesManagerId' => 'sometimes|required|integer|exists:users,id',
            'financeManagerId' => 'sometimes|required|integer|exists:users,id',
            'marketingManagerId' => 'sometimes|required|integer|exists:users,id',
            'customerServiceManagerId' => 'sometimes|required|integer|exists:users,id',
        ], [
            'idClient.required' => 'El idClient es requerido',
            'idClient.unique' => 'El idClient ya existe',
            'area.in' => 'El area debe ser sales o aftermarket',
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
