<?php

use App\Http\Controllers\Api\RequestController;
use Illuminate\Support\Facades\Route;


Route::middleware(['jwt'])->group(function () {
    Route::get('requests', [RequestController::class, 'getAll']);
    Route::get('requests/drafts', [RequestController::class, 'getDrafts']);
    Route::get('requests/pending/me', [RequestController::class, 'getMyPending']);
    Route::get('requests/pending/{id}', [RequestController::class, 'getPendingByRole']);
    Route::get('requests/{requestId}/history', [RequestController::class, 'getRequestHistoryById']);
    Route::get('requests/{requestId}/attachments', [RequestController::class, 'getAttachmentsByRequestId']);
    Route::get('requests/attachments/{attachmentId}', [RequestController::class, 'getAttachmentById']);
    Route::delete('requests/{requestId}/attachments/{attachmentId}', [RequestController::class, 'deleteAttachmentById']);
    Route::get('requests/reasons', [RequestController::class, 'getAllReasons']);
    Route::get('requests/next-number/{requestTypeId}', [RequestController::class, 'getNextRequestNumber']);
    Route::get('requests/{id}', [RequestController::class, 'getAllByRequestType']);
    Route::post('requests/draft', [RequestController::class, 'saveDraft']);
    Route::post('requests/newRequest', [RequestController::class, 'createRequest']);
    Route::post('requests/{requestId}/approve', [RequestController::class, 'approve']);
    Route::post('requests/{requestId}/reject', [RequestController::class, 'reject']);
});
