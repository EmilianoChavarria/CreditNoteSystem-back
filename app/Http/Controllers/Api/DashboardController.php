<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Request as RequestModel;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
class DashboardController extends Controller
{
    public function getDays(Request $request)
    {
        $inicio = Carbon::now()->subDays(30)->startOfDay();
        $fin = Carbon::now()->endOfDay();
        $user = $request->attributes->get('authUser');
        // var_dump($request);

        $conteos = RequestModel::whereBetween('createdAt', [$inicio, $fin])
            ->selectRaw('DATE(createdAt) as fecha, count(*) as total')
            ->where('userId', $user->id)
            ->groupBy('fecha')
            ->pluck('total', 'fecha');

        $periodo = \Carbon\CarbonPeriod::create($inicio, $fin);

        $resultado = [];
        foreach ($periodo as $fecha) {
            $fechaString = $fecha->format('Y-m-d');

            $resultado[] = [
                'dia' => $fechaString,
                'cantidad' => $conteos->get($fechaString, 0)
            ];
        }

        return response()->json(ApiResponse::success('Conteo de Requests por día', $resultado));
    }

}
