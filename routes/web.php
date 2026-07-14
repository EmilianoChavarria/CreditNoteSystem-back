<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;


Route::get('/', function () {
    return view('welcome');
});

Route::post('/deploy/finish', function (Request $request) {
    if ($request->header('X-Deploy-Token') !== env('DEPLOY_TOKEN')) {
        abort(403);
    }

    Artisan::call('package:discover', ['--ansi' => true]);
    // Artisan::call('migrate', ['--force' => true]);
    Artisan::call('config:cache');
    Artisan::call('route:cache');
    Artisan::call('view:cache');

    return response()->json(['status' => 'ok']);
});