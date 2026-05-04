<?php

namespace App\Http\Controllers\Api;

use App\Actions\Requests\ApproveMassRequestsAction;
use App\Actions\Requests\ApproveRequestAction;
use App\Actions\Requests\RejectMassRequestsAction;
use App\Actions\Requests\RejectRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requests\ApproveMassRequestInput;
use App\Http\Requests\Requests\ApproveRequestInput;
use App\Http\Requests\Requests\CreateRequestInput;
use App\Http\Requests\Requests\RejectMassRequestInput;
use App\Http\Requests\Requests\RejectRequestInput;
use App\Http\Requests\Requests\SaveDraftRequestInput;
use App\Http\Requests\Requests\UpdateRequestInput;
use App\Http\Resources\RequestAttachmentResource;
use App\Http\Resources\RequestReasonResource;
use App\Http\Resources\RequestResource;
use App\Models\Request as RequestModel;
use App\Models\RequestReason;
use App\Models\RequestType;
use App\Services\RequestAttachmentService;
use App\Services\RequestCrudService;
use App\Services\RequestHistoryService;
use App\Services\RequestNumberService;
use App\Services\RequestWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class RequestController extends Controller
{
    public function __construct(
        private readonly RequestNumberService $requestNumberService,
        private readonly RequestHistoryService $requestHistoryService,
        private readonly RequestAttachmentService $requestAttachmentService,
        private readonly RequestCrudService $requestCrudService,
        private readonly RequestWorkflowService $requestWorkflowService,
        private readonly ApproveRequestAction $approveRequestAction,
        private readonly RejectRequestAction $rejectRequestAction,
        private readonly ApproveMassRequestsAction $approveMassRequestsAction,
        private readonly RejectMassRequestsAction $rejectMassRequestsAction,
    )
    {
    }

    public function getRequestHistoryById(int $requestId)
    {
        try {
            $history = $this->requestHistoryService->getHistoryByRequestId($requestId);

            return response()->json(ApiResponse::success('Request history', $history));
        } catch (ModelNotFoundException $e) {
            return response()->json(ApiResponse::error('Request no encontrada', null, 404), 404);
        }
    }

    public function getAttachmentsByRequestId(int $requestId)
    {
        $requestModel = $this->requestAttachmentService->findRequest($requestId);

        if (!$requestModel) {
            return response()->json(ApiResponse::error('Request no encontrada', null, 404), 404);
        }

        $attachments = $this->requestAttachmentService->getActiveAttachmentsByRequestId($requestId);

        return response()->json(ApiResponse::success('Adjuntos de la solicitud', [
            'requestId' => $requestId,
            'total' => $attachments->count(),
            'attachments' => RequestAttachmentResource::collection($attachments),
        ]));
    }

    public function getAttachmentById(int $attachmentId)
    {
        $attachment = $this->requestAttachmentService->findActiveAttachmentById($attachmentId);

        if (!$attachment) {
            return response()->json(ApiResponse::error('Adjunto no encontrado', null, 404), 404);
        }

        $publicUrl = URL::temporarySignedRoute(
            'attachments.preview',
            now()->addMinutes(15),
            ['attachmentId' => (int) $attachment->id]
        );

        return response()->json(ApiResponse::success('Adjunto', [
            'attachment' => RequestAttachmentResource::make($attachment),
            'fileUrl' => $publicUrl,
        ]));
    }

    public function getAttachmentPreviewLinkById(int $attachmentId)
    {
        $attachment = $this->requestAttachmentService->findActiveAttachmentById($attachmentId);

        if (!$attachment) {
            return response()->json(ApiResponse::error('Adjunto no encontrado', null, 404), 404);
        }

        $previewUrl = URL::temporarySignedRoute(
            'attachments.preview',
            now()->addMinutes(15),
            ['attachmentId' => (int) $attachment->id]
        );

        return response()->json(ApiResponse::success('Link de vista previa del adjunto', [
            'attachmentId' => (int) $attachment->id,
            'fileName' => (string) $attachment->fileName,
            'previewUrl' => $previewUrl,
        ]));
    }

    public function previewAttachment(int $attachmentId, Request $request)
    {
        $attachment = $this->requestAttachmentService->findActiveAttachmentById($attachmentId);

        if (!$attachment) {
            return response()->json(ApiResponse::error('Adjunto no encontrado', null, 404), 404);
        }

        $path = (string) ($attachment->filePath ?? '');
        if ($path === '') {
            return response()->json(ApiResponse::error('Adjunto sin ruta de archivo', null, 422), 422);
        }

        foreach (['public', 'local'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    $absolutePath = Storage::disk($disk)->path($path);

                    return response()->file($absolutePath, [
                        'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
                    ]);
                }
            } catch (\Throwable $e) {
                // intentar siguiente disco
            }
        }

        return response()->json(ApiResponse::error('No se encontró el archivo en almacenamiento', [
            'attachmentId' => $attachmentId,
        ], 404), 404);
    }

    public function deleteAttachmentById(int $requestId, int $attachmentId)
    {
        $requestModel = $this->requestAttachmentService->findRequest($requestId);

        if (!$requestModel) {
            return response()->json(ApiResponse::error('Request no encontrada', null, 404), 404);
        }

        $attachment = $this->requestAttachmentService->findActiveAttachmentByIdAndRequest($attachmentId, $requestId);

        if (!$attachment) {
            return response()->json(ApiResponse::error('Adjunto no encontrado', null, 404), 404);
        }

        $deleteMeta = $this->requestAttachmentService->deleteAttachment($requestId, $attachmentId);

        return response()->json(ApiResponse::success('Adjunto eliminado correctamente', [
            'requestId' => $requestId,
            'attachmentId' => $attachmentId,
            'logicalDelete' => (bool) $deleteMeta['logicalDelete'],
        ]));
    }

    public function getPendingByRole(Request $request, int $id)
    {
        $authUser = $request->attributes->get('authUser');
        $isAdmin = $this->isAdminUser($authUser);

        $requests = RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification',
            'workflowCurrentStep.workflowStep',
            'workflowCurrentStep.assignedRole',
            'workflowCurrentStep.assignedUser',
        ])
            ->whereHas('workflowCurrentStep', function ($query) use ($authUser, $isAdmin) {
                $query->where('status', 'pending');

                if (!$isAdmin) {
                    $query->where('assignedUserId', (int) $authUser->id);
                }
            })
            // ->where('status', 'created')
            ->where('requestTypeId', $id)
            ->orderBy('id')
            ->get();

        return response()->json(ApiResponse::success('Pending requests for your role', RequestResource::collection($requests)));
    }

    public function getMyPending(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id, $authUser->roleId)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $isAdmin = $this->isAdminUser($authUser);
        $requestTypeId = $request->filled('requestTypeId') ? (int) $request->input('requestTypeId') : null;
        $search = trim((string) $request->query('search', ''));
        $perPageInput = $request->query('per_page', $request->query('perPage', 15));
        $perPage = max(1, (int) $perPageInput);
        $page = max(1, (int) $request->query('page', 1));
        $requests = $this->requestCrudService->getMyPending($authUser, $isAdmin, $requestTypeId, $search, $perPage, $page);
        $requests->setCollection(RequestResource::collection($requests->getCollection())->collection);

        return response()->json(ApiResponse::success('Pending requests for current user', $requests));
    }

    public function getAll()
    {
        $requests = RequestModel::with([
            'requestType',
            'user',
            'reason',
            'classification'
        ])->orderBy('id')->get();

        return response()->json(ApiResponse::success('Requests', RequestResource::collection($requests)));
    }

    public function getAllByRequestType(Request $request, int $id)
    {
        $perPage = max(1, (int) $request->query('per_page', 15));
        $search = trim((string) $request->query('search', ''));
        $requests = $this->requestCrudService->getByRequestType($id, $perPage, $search);
        $requests->setCollection(RequestResource::collection($requests->getCollection())->collection);

        return response()->json(ApiResponse::success('Requests', $requests));
    }

    public function getAllReasonsByRequestType(int $requestTypeId)
    {
        $requestType = RequestType::find($requestTypeId);

        if (!$requestType) {
            return response()->json(ApiResponse::error('Request type not found', null, 404), 404);
        }

        $reasons = $requestType->requestReasons;

        return response()->json(ApiResponse::success("Reasons", RequestReasonResource::collection($reasons)));
    }

    public function getNextRequestNumber(int $requestTypeId)
    {
        if ($requestTypeId <= 0) {
            return response()->json(
                ApiResponse::error('requestTypeId inválido', ['requestTypeId' => ['Debe ser un número entero positivo']], 422),
                422
            );
        }

        $requestNumber = $this->requestNumberService->generateRequestNumber($requestTypeId);

        return response()->json(ApiResponse::success('Next request number', [
            'requestTypeId' => $requestTypeId,
            'requestNumber' => $requestNumber,
            'prefix' => $this->requestNumberService->getPrefixForType($requestTypeId),
        ], 201), 201);
    }

    public function createRequest(CreateRequestInput $request)
    {
        $user    = $request->attributes->get('authUser');
        $created = $this->requestCrudService->createRequest($request->validated(), $user);

        foreach (['uploadSupport', 'sapScreen'] as $fileType) {
            if ($request->hasFile($fileType)) {
                $files = $request->file($fileType);
                $this->requestAttachmentService->storeAndAttachFiles(
                    $created,
                    \is_array($files) ? $files : [$files],
                    $fileType
                );
            }
        }

        return response()->json(ApiResponse::success('Request creado', RequestResource::make($created->refresh()), 201), 201);
    }

    public function updateRequest(UpdateRequestInput $request, int $requestId)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id, $authUser->roleId)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $isAdmin = $this->isAdminUser($authUser);
        $result = $this->requestCrudService->updateRequest($requestId, $request->validated(), $authUser, $isAdmin);

        if ($result['status'] >= 400) {
            return response()->json(ApiResponse::error($result['message'], $result['errors'] ?? null, $result['status']), $result['status']);
        }

        foreach (['uploadSupport', 'sapScreen'] as $fileType) {
            if ($request->hasFile($fileType)) {
                $files = $request->file($fileType);
                $this->requestAttachmentService->storeAndAttachFiles(
                    $result['data'],
                    \is_array($files) ? $files : [$files],
                    $fileType
                );
            }
        }

        return response()->json(ApiResponse::success($result['message'], RequestResource::make($result['data']->refresh()), $result['status']), $result['status']);
    }

    public function approve(ApproveRequestInput $request, int $requestId)
    {
        $authUser = $request->attributes->get('authUser');
        $isAdmin = $this->isAdminUser($authUser);

        $result = $this->approveRequestAction->execute($requestId, $authUser, $isAdmin, $request->input('comments'));

        if (!$result['ok']) {
            return response()->json(ApiResponse::error($result['payload']['message'], null, $result['status']), $result['status']);
        }

        if (!empty($result['notifyUserId'])) {
            $this->requestWorkflowService->notifyAssignedUser($requestId);
        }

        return response()->json(ApiResponse::success($result['payload']['message'], RequestResource::make($result['payload']['data'])), $result['status']);
    }

    public function reject(RejectRequestInput $request, int $requestId)
    {
        $authUser = $request->attributes->get('authUser');
        $isAdmin = $this->isAdminUser($authUser);

        $result = $this->rejectRequestAction->execute($requestId, $authUser, $isAdmin, (string) $request->input('comments'));

        if (!$result['ok']) {
            return response()->json(ApiResponse::error($result['payload']['message'], null, $result['status']), $result['status']);
        }

        return response()->json(ApiResponse::success($result['payload']['message'], RequestResource::make($result['payload']['data'])), $result['status']);
    }

    public function approveMass(ApproveMassRequestInput $request)
    {
        $authUser = $request->attributes->get('authUser');
        $isAdmin = $this->isAdminUser($authUser);
        $requestIds = array_values(array_unique(array_map('intval', (array) $request->input('requestIds', []))));
        $result = $this->approveMassRequestsAction->execute($requestIds, $authUser, $isAdmin, $request->input('comments'));

        return response()->json(ApiResponse::success('Aprobacion masiva procesada', [
            'totalReceived' => $result['totalReceived'],
            'totalApproved' => $result['totalApproved'],
            'totalFailed' => $result['totalFailed'],
            'approvedRequestIds' => $result['approvedRequestIds'],
            'failedRequests' => $result['failedRequests'],
        ]));
    }

    public function rejectMass(RejectMassRequestInput $request)
    {
        $authUser = $request->attributes->get('authUser');
        $isAdmin = $this->isAdminUser($authUser);
        $requestIds = array_values(array_unique(array_map('intval', (array) $request->input('requestIds', []))));
        $globalComment = (string) $request->input('comments');
        $result = $this->rejectMassRequestsAction->execute($requestIds, $authUser, $isAdmin, $globalComment);

        return response()->json(ApiResponse::success('Rechazo masivo procesado', [
            'totalReceived' => $result['totalReceived'],
            'totalRejected' => $result['totalRejected'],
            'totalFailed' => $result['totalFailed'],
            'rejectedRequestIds' => $result['rejectedRequestIds'],
            'failedRequests' => $result['failedRequests'],
            'commentApplied' => $result['commentApplied'],
        ]));
    }

    private function isAdminUser(mixed $authUser): bool
    {
        $roleName = mb_strtoupper(trim((string) ($authUser->roleName ?? '')));

        return str_contains($roleName, 'ADMIN');
    }

    public function getByCustomerId(Request $request, string $customerId)
    {
        $perPage = max(1, (int) $request->query('perPage', $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));

        $requests = $this->requestCrudService->getByCustomerId($customerId, $perPage, $page);
        $requests->setCollection(RequestResource::collection($requests->getCollection())->collection);

        return response()->json(ApiResponse::success('Solicitudes del cliente', $requests));
    }

    public function saveDraft(SaveDraftRequestInput $request)
    {
        $user = $request->attributes->get('authUser');

        $result = $this->requestCrudService->saveDraft($request->validated(), $user);

        if ($result['status'] >= 400) {
            return response()->json(ApiResponse::error($result['message'], null, $result['status']), $result['status']);
        }

        return response()->json(ApiResponse::success($result['message'], RequestResource::make($result['data']), $result['status']), $result['status']);
    }

    public function getDrafts(Request $request)
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser || !isset($authUser->id)) {
            return response()->json(ApiResponse::error('Usuario no autenticado', null, 401), 401);
        }

        $perPage = $request->query('perPage', 15);
        $page = $request->query('page', 1);

        try {
            $drafts = $this->requestCrudService->getDrafts((int) $authUser->id, (int) $perPage, (int) $page);
            $drafts->setCollection(RequestResource::collection($drafts->getCollection())->collection);

            return response()->json(ApiResponse::success('Borradores', $drafts));
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al obtener borradores', ['error' => $e->getMessage()], 500), 500);
        }
    }

}
