<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = Module::orderBy('id')->get();

        return response()->json(ApiResponse::success('Modules', ModuleResource::collection($modules)));
    }

    public function show(int $id)
    {
        $module = Module::find($id);

        if (!$module) {
            return response()->json(ApiResponse::error('Module no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Module', ModuleResource::make($module)));
    }

    public function store(Request $request)
    {
        $payload = [
            'name' => $request->input('name', $request->input('moduleName')),
            // 'moduleName' => $request->input('moduleName', $request->input('name')),
            'parentid' => $request->input('parentid'),
            'url' => $request->input('url'),
            'icon' => $request->input('icon'),
            'orderindex' => $request->input('orderindex', 0),
            'requiredactionid' => $request->input('requiredactionid'),
        ];

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:100'],
            // 'moduleName' => ['nullable', 'string', 'max:150'],
            'parentid' => ['nullable', 'int', 'exists:modules,id'],
            'url' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'orderindex' => ['nullable', 'integer', 'min:0'],
            'requiredactionid' => ['nullable', 'int', 'exists:actions,id'],
        ]);

        $validator->after(function ($validator) use ($payload) {
            if (blank($payload['name'])) {
                $validator->errors()->add('name', 'Debe enviar name.');
            }
        });

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $module = Module::create($payload);

        return response()->json(ApiResponse::success('Module creado', ModuleResource::make($module), 201), 201);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('modules', 'name')->ignore($id),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $module = Module::find($id);

        if (!$module) {
            return response()->json(ApiResponse::error('Module no encontrado', null, 404), 404);
        }

        $module->update([
            'name' => $request->input('name'),
        ]);

        $module->refresh();

        return response()->json(ApiResponse::success('Module actualizado', ModuleResource::make($module)));
    }

    public function destroy(int $id)
    {
        $module = DB::table('modules')->where('id', $id)->first();

        if (!$module) {
            return response()->json(ApiResponse::error('Module no encontrado', null, 404), 404);
        }

        DB::table('modules')->where('id', $id)->delete();

        return response()->json(ApiResponse::success('Module eliminado'));
    }

}
