<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function getAll()
    {
        $invoices = DB::table('comprobantes_tme700618rc7')->get();

        return response()->json(ApiResponse::success('Facturas', $invoices));
    }
}