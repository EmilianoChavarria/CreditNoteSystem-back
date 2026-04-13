<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\RequestAttachment;
use Illuminate\Support\Facades\Schema;

class RequestAttachmentService
{
    public function findRequest(int $requestId): ?RequestModel
    {
        return RequestModel::query()->find($requestId);
    }

    public function getActiveAttachmentsByRequestId(int $requestId)
    {
        return RequestAttachment::query()
            ->where('requestId', $requestId)
            ->when(
                Schema::hasColumn((new RequestAttachment())->getTable(), 'isActive'),
                fn ($query) => $query->where('isActive', true)
            )
            ->when(
                Schema::hasColumn((new RequestAttachment())->getTable(), 'deletedAt'),
                fn ($query) => $query->whereNull('deletedAt')
            )
            ->orderByDesc('id')
            ->get();
    }

    public function findActiveAttachmentById(int $attachmentId): ?RequestAttachment
    {
        return RequestAttachment::query()
            ->where('id', $attachmentId)
            ->when(
                Schema::hasColumn((new RequestAttachment())->getTable(), 'isActive'),
                fn ($query) => $query->where('isActive', true)
            )
            ->when(
                Schema::hasColumn((new RequestAttachment())->getTable(), 'deletedAt'),
                fn ($query) => $query->whereNull('deletedAt')
            )
            ->first();
    }

    public function findActiveAttachmentByIdAndRequest(int $attachmentId, int $requestId): ?RequestAttachment
    {
        return RequestAttachment::query()
            ->where('id', $attachmentId)
            ->where('requestId', $requestId)
            ->when(
                Schema::hasColumn((new RequestAttachment())->getTable(), 'isActive'),
                fn ($query) => $query->where('isActive', true)
            )
            ->when(
                Schema::hasColumn((new RequestAttachment())->getTable(), 'deletedAt'),
                fn ($query) => $query->whereNull('deletedAt')
            )
            ->first();
    }

    public function deleteAttachment(int $requestId, int $attachmentId): array
    {
        $hasIsActive = Schema::hasColumn((new RequestAttachment())->getTable(), 'isActive');
        $hasDeletedAt = Schema::hasColumn((new RequestAttachment())->getTable(), 'deletedAt');

        if (!$hasIsActive && !$hasDeletedAt) {
            RequestAttachment::query()
                ->where('id', $attachmentId)
                ->where('requestId', $requestId)
                ->delete();

            return ['logicalDelete' => false];
        }

        $updateData = [];

        if ($hasIsActive) {
            $updateData['isActive'] = false;
        }

        if ($hasDeletedAt) {
            $updateData['deletedAt'] = now();
        }

        RequestAttachment::query()
            ->where('id', $attachmentId)
            ->where('requestId', $requestId)
            ->update($updateData);

        return ['logicalDelete' => true];
    }
}