<?php

namespace App\Services;

use App\Models\Request as RequestModel;
use App\Models\RequestAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * @param UploadedFile[] $files
     */
    public function storeAndAttachFiles(RequestModel $request, array $files, string $fileType): void
    {
        $configKey = $fileType === 'sapScreen' ? 'sap_screen' : 'upload_support';
        $disk      = (string) Config::get("bulk_upload.{$configKey}.disk", 'public');
        $basePath  = trim((string) Config::get("bulk_upload.{$configKey}.path", $fileType), '/');

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $extension  = strtolower((string) $file->getClientOriginalExtension());
            $path       = $basePath . '/' . now()->format('Y/m/d') . '/' . Str::uuid() . '.' . $extension;

            Storage::disk($disk)->put($path, (string) file_get_contents($file->getRealPath()));

            RequestAttachment::create([
                'requestId'     => $request->id,
                'fileName'      => $file->getClientOriginalName(),
                'fileSize'      => $file->getSize(),
                'filePath'      => $path,
                'fileExtension' => $extension,
                'fileType'      => $fileType,
                'isActive'      => true,
                'deletedAt'     => null,
            ]);
        }
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