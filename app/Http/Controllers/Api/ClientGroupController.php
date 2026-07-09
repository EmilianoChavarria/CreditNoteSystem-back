<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClientGroupService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ClientGroupController extends Controller
{
    public function __construct(private readonly ClientGroupService $service) {}

    public function index()
    {
        return response()->json(ApiResponse::success('Grupos de clientes', $this->service->all()));
    }

    public function store(Request $request)
    {
        $data  = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
            'responsibleUserId' => 'nullable|integer|exists:users,id',
        ]);
        $group = $this->service->create($data['name'], $data['description'] ?? null, $data['responsibleUserId'] ?? null);

        return response()->json(ApiResponse::success('Grupo creado', $group), 201);
    }

    public function update(Request $request, int $id)
    {
        $data  = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
            'responsibleUserId' => 'nullable|integer|exists:users,id',
        ]);
        $group = $this->service->update($id, $data['name'], $data['description'] ?? null, $data['responsibleUserId'] ?? null);

        return response()->json(ApiResponse::success('Grupo actualizado', $group));
    }

    public function destroy(int $id)
    {
        $this->service->delete($id);

        return response()->json(ApiResponse::success('Grupo eliminado'));
    }

    public function members(int $id)
    {
        return response()->json(ApiResponse::success('Miembros del grupo', $this->service->getMembers($id)));
    }

    public function addMember(Request $request, string $id)
    {
        $data = $request->validate(['clientId' => 'required|string']);
        $this->service->addMember($id, $data['clientId']);

        return response()->json(ApiResponse::success('Miembro agregado'));
    }

    public function addMembersBulk(Request $request, string $id)
    {
        $data = $request->validate(['clientIds' => 'required|array|min:1']);
        $this->service->addMembers($id, $data['clientIds']);

        return response()->json(ApiResponse::success('Miembros agregados'));
    }

    public function removeMember(int $id, int $clientId)
    {
        $this->service->removeMember($id, $clientId);

        return response()->json(ApiResponse::success('Miembro eliminado'));
    }

    public function forecastSummary(int $id, int $year)
    {
        $summary = $this->service->getForecastSummary($id, $year);

        return response()->json(ApiResponse::success('Resumen forecast grupo', $summary));
    }
}
