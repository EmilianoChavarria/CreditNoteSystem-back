<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ActionController extends Controller
{
    public function getAll()
    {
        $actions = Action::all();

        return response()->json(ApiResponse::success('Actions', $actions));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:50', 'unique:actions,name'],
            'slug' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:actions,slug'],
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error('Datos inválidos', $validator->errors(), 422), 422);
        }

        $action = Action::create([
            'name' => $request->input('name'),
            'slug' => strtolower((string) $request->input('slug')),
        ]);

        return response()->json(ApiResponse::success('Action creada', $action, 201), 201);
    }
}
