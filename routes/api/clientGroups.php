<?php

use App\Http\Controllers\Api\ClientGroupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt'])->group(function () {
    Route::get('client-groups', [ClientGroupController::class, 'index']);
    Route::post('client-groups', [ClientGroupController::class, 'store']);
    Route::put('client-groups/{id}', [ClientGroupController::class, 'update'])->whereNumber('id');
    Route::delete('client-groups/{id}', [ClientGroupController::class, 'destroy'])->whereNumber('id');

    Route::get('client-groups/{id}/members', [ClientGroupController::class, 'members'])->whereNumber('id');
    Route::post('client-groups/{id}/members', [ClientGroupController::class, 'addMember'])->whereNumber('id');
    Route::post('client-groups/{id}/members/bulk', [ClientGroupController::class, 'addMembersBulk'])->whereNumber('id');
    Route::delete('client-groups/{id}/members/{clientId}', [ClientGroupController::class, 'removeMember'])->whereNumber(['id', 'clientId']);

    Route::get('client-groups/{id}/{year}/forecast', [ClientGroupController::class, 'forecastSummary'])->whereNumber(['id', 'year']);
});
