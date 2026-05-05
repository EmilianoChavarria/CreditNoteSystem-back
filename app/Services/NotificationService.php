<?php

namespace App\Services;

use App\Events\SocketMessageSent;
use App\Models\Batch;
use App\Models\Notification as NotificationModel;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function createAssignedRequestNotification(RequestModel $requestModel, int $userId): ?NotificationModel
    {
        $requestNumber = (string) ($requestModel->requestNumber ?? $requestModel->id);

        $notification = $this->createAndBroadcast(
            userId: $userId,
            type: 'assigned_request',
            relatedId: (int) $requestModel->id,
            title: 'Tienes una solicitud pendiente por aprobar',
            message: "La solicitud #{$requestNumber} fue asignada a tu bandeja para revisión."
        );

        // Emitir evento específico de request asignado
        if ($notification) {
            $this->broadcastRequestAssigned($requestModel, $userId);
        }

        return $notification;
    }

    private function broadcastRequestAssigned(RequestModel $requestModel, int $userId): void
    {
        try {
            broadcast(new SocketMessageSent([
                'event' => 'request.assigned',
                'targetUserId' => $userId,
                'request' => [
                    'id' => (int) $requestModel->id,
                    'requestNumber' => (string) $requestModel->requestNumber,
                    'status' => (string) $requestModel->status,
                    'customerId' => $requestModel->customerId !== null ? (int) $requestModel->customerId : null,
                    'requestTypeId' => (int) $requestModel->requestTypeId,
                    'amount' => (float) $requestModel->amount,
                    'totalAmount' => (float) $requestModel->totalAmount,
                    'createdAt' => $requestModel->createdAt?->toIso8601String(),
                ],
                'sentAt' => now()->toIso8601String(),
            ]));

            Log::info('Evento request.assigned emitido por socket', [
                'requestId' => (int) $requestModel->id,
                'userId' => $userId,
            ]);
        } catch (Throwable $e) {
            Log::error('Error emitiendo evento request.assigned', [
                'requestId' => (int) $requestModel->id,
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function createBatchFinishedNotification(Batch $batch): ?NotificationModel
    {
        $isCompleted = (string) $batch->status === 'completed';

        $notification = $this->createAndBroadcast(
            userId: (int) $batch->userId,
            type: 'batch_finished',
            relatedId: (int) $batch->id,
            title: $isCompleted
                ? 'Tu carga masiva ha finalizado'
                : 'Tu carga masiva finalizó con errores',
            message: $isCompleted
                ? "El batch #{$batch->id} ya fue procesado correctamente."
                : "El batch #{$batch->id} finalizó con errores y requiere revisión."
        );

        // Emitir evento específico de batch finalizado para que Angular lo detecte y refresque
        if ($notification) {
            $this->broadcastBatchFinished($batch);
        }

        return $notification;
    }

    /**
     * Crea una notificación resumida para asignaciones masivas de solicitudes.
     *
     * @param array<int, string|int> $requestNumbers
     */
    public function createAssignedRequestsSummaryNotification(int $userId, array $requestNumbers): ?NotificationModel
    {
        $requestNumbers = array_values(array_filter(array_unique(array_map(static fn ($value) => trim((string) $value), $requestNumbers))));
        $total = count($requestNumbers);

        if ($total <= 0) {
            return null;
        }

        $preview = implode(', ', array_slice($requestNumbers, 0, 5));
        $remaining = max(0, $total - 5);

        $message = "Tienes {$total} solicitudes nuevas pendientes por aprobar.";
        if ($preview !== '') {
            $message .= " Solicitudes: {$preview}";
            if ($remaining > 0) {
                $message .= " y {$remaining} mas.";
            }
            $message .= '.';
        }

        $notification = $this->createAndBroadcast(
            userId: $userId,
            type: 'assigned_request_bulk',
            relatedId: null,
            title: 'Tienes nuevas solicitudes pendientes por aprobar',
            message: $message
        );

        if ($notification) {
            $this->broadcastRequestAssignedBulk($userId, $total, $requestNumbers);
        }

        return $notification;
    }

    private function broadcastBatchFinished(Batch $batch): void
    {
        try {
            broadcast(new SocketMessageSent([
                'event' => 'batch.finished',
                'targetUserId' => (int) $batch->userId,
                'batch' => [
                    'id' => (int) $batch->id,
                    'batchType' => (string) $batch->batchType,
                    'status' => (string) $batch->status,
                    'totalRecords' => (int) $batch->totalRecords,
                    'processedRecords' => (int) $batch->processedRecords,
                    'errorRecords' => (int) $batch->errorRecords,
                    'processingRecords' => (int) $batch->processingRecords,
                    'fileName' => (string) $batch->fileName,
                    'createdAt' => $batch->createdAt?->toIso8601String(),
                    'updatedAt' => $batch->updatedAt?->toIso8601String(),
                ],
                'sentAt' => now()->toIso8601String(),
            ]));

            Log::info('Evento batch.finished emitido por socket', [
                'batchId' => (int) $batch->id,
                'userId' => (int) $batch->userId,
                'status' => (string) $batch->status,
            ]);
        } catch (Throwable $e) {
            Log::error('Error emitiendo evento batch.finished', [
                'batchId' => (int) $batch->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<int, string> $requestNumbers
     */
    private function broadcastRequestAssignedBulk(int $userId, int $total, array $requestNumbers): void
    {
        try {
            broadcast(new SocketMessageSent([
                'event' => 'request.assigned.bulk',
                'targetUserId' => $userId,
                'summary' => [
                    'total' => $total,
                    'requestNumbers' => array_slice($requestNumbers, 0, 10),
                ],
                'sentAt' => now()->toIso8601String(),
            ]));

            Log::info('Evento request.assigned.bulk emitido por socket', [
                'userId' => $userId,
                'total' => $total,
            ]);
        } catch (Throwable $e) {
            Log::error('Error emitiendo evento request.assigned.bulk', [
                'userId' => $userId,
                'total' => $total,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createAndBroadcast(int $userId, string $type, ?int $relatedId, string $title, string $message): ?NotificationModel
    {
        if ($userId <= 0) {
            Log::warning('NotificationService: userId inválido', ['userId' => $userId]);
            return null;
        }

        try {
            $user = User::query()->find($userId);
            if (!$user) {
                Log::warning('NotificationService: usuario no encontrado', ['userId' => $userId]);
                return null;
            }

            $notification = NotificationModel::create([
                'userId' => $userId,
                'type' => $type,
                'relatedId' => $relatedId,
                'title' => $title,
                'message' => $message,
                'isRead' => false,
                'readAt' => null,
            ]);

            Log::info('Notificación creada exitosamente', [
                'notificationId' => (int) $notification->id,
                'userId' => $userId,
                'type' => $type,
            ]);

            broadcast(new SocketMessageSent([
                'event' => 'notification.created',
                'targetUserId' => $userId,
                'notification' => [
                    'id' => (int) $notification->id,
                    'userId' => (int) $notification->userId,
                    'type' => (string) $notification->type,
                    'relatedId' => $notification->relatedId !== null ? (int) $notification->relatedId : null,
                    'title' => (string) $notification->title,
                    'message' => (string) $notification->message,
                    'isRead' => (bool) $notification->isRead,
                    'readAt' => $notification->readAt?->toIso8601String(),
                    'createdAt' => $notification->createdAt?->toIso8601String(),
                    'updatedAt' => $notification->updatedAt?->toIso8601String(),
                ],
                'sentAt' => now()->toIso8601String(),
            ]));

            return $notification;
        } catch (Throwable $e) {
            Log::error('No se pudo crear o emitir la notificacion', [
                'userId' => $userId,
                'type' => $type,
                'relatedId' => $relatedId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}