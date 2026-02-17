<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    public function index()
    {
        $modules = DB::table('modules')->orderBy('id')->get();

        return response()->json(ApiResponse::success('Modules', $modules));
    }

    public function show(int $id)
    {
        $role = DB::table('modules')->where('id', $id)->first();

        if (!$role) {
            return response()->json(ApiResponse::error('Module no encontrado', null, 404), 404);
        }

        return response()->json(ApiResponse::success('Module', $role));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'moduleName' => ['required', 'string', 'max:150', 'unique:modules,moduleName'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $id = DB::table('modules')->insertGetId([
            'moduleName' => $request->input('moduleName'),
        ]);

        $role = DB::table('modules')->where('id', $id)->first();

        return response()->json(ApiResponse::success('Module creado', $role, 201), 201);
    }

    public function update(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'moduleName' => [
                'required',
                'string',
                'max:150',
                Rule::unique('modules', 'moduleName')->ignore($id),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $role = DB::table('modules')->where('id', $id)->first();

        if (!$role) {
            return response()->json(ApiResponse::error('Module no encontrado', null, 404), 404);
        }

        DB::table('modules')->where('id', $id)->update([
            'moduleName' => $request->input('moduleName'),
        ]);

        $role = DB::table('modules')->where('id', $id)->first();

        return response()->json(ApiResponse::success('Module actualizado', $role));
    }

    public function destroy(int $id)
    {
        $role = DB::table('modules')->where('id', $id)->first();

        if (!$role) {
            return response()->json(ApiResponse::error('Module no encontrado', null, 404), 404);
        }

        DB::table('modules')->where('id', $id)->delete();

        return response()->json(ApiResponse::success('Module eliminado'));
    }

}
