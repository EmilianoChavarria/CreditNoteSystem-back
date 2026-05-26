<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataExportService;
use App\Services\SimpleExcelExportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExportController extends Controller
{
    public function __construct(
        private readonly DataExportService $dataExportService,
        private readonly SimpleExcelExportService $excelExportService,
    ) {
    }

    public function excel(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        try {
            $export = $this->dataExportService->build(
                (string) $request->query('module', ''),
                $request->query(),
                $authUser
            );

            $content = $this->excelExportService->build(
                $export['headers'],
                $export['rows'],
                $export['sheetName']
            );

            return response($content, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $export['filename'] . '"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (ValidationException $e) {
            return response()->json(ApiResponse::error('Datos invalidos', $e->errors(), 422), 422);
        } catch (Throwable $e) {
            return response()->json(ApiResponse::error('No se pudo generar el Excel', [
                'message' => $e->getMessage(),
            ], 422), 422);
        }
    }
}
