<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChargeType;
use App\Support\ApiResponse;

class ChargeTypeController extends Controller
{
    public function index()
    {
        $chargeTypes = ChargeType::all();

        return response()->json(ApiResponse::success('Tipos de cargo', $chargeTypes));
    }
}
