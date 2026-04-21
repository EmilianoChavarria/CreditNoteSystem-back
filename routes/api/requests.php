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
    Route::get('requests/attachments/{attachmentId}/preview-link', [RequestController::class, 'getAttachmentPreviewLinkById']);
    Route::delete('requests/{requestId}/attachments/{attachmentId}', [RequestController::class, 'deleteAttachmentById']);
    Route::get('requests/reasons/{requestTypeId}', [RequestController::class, 'getAllReasonsByRequestType']);
    Route::get('requests/next-number/{requestTypeId}', [RequestController::class, 'getNextRequestNumber']);
    Route::get('requests/customer/{customerId}', [RequestController::class, 'getByCustomerId']);
    Route::get('requests/{id}', [RequestController::class, 'getAllByRequestType']);
    Route::post('requests/draft', [RequestController::class, 'saveDraft']);
    Route::post('requests/newRequest', [RequestController::class, 'createRequest']);
    Route::put('requests/{requestId}', [RequestController::class, 'updateRequest']);
    Route::post('requests/approve-mass', [RequestController::class, 'approveMass']);
    Route::post('requests/reject-mass', [RequestController::class, 'rejectMass']);
    Route::post('requests/{requestId}/approve', [RequestController::class, 'approve']);
    Route::post('requests/{requestId}/reject', [RequestController::class, 'reject']);
});

Route::get('attachments/{attachmentId}/preview', [RequestController::class, 'previewAttachment'])
    ->name('attachments.preview')
    ->middleware('signed');
