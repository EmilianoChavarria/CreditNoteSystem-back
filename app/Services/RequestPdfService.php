<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RequestPdfService
{
    private const INVOICES_CONNECTION = 'invoices';
    private const CLIENT_TABLE = 'clientes_TME700618RC7';

    public function generatePdf(int $requestId): mixed
    {
        $request = Request::with([
            'requestType',
            'user',
            'reason',
            'classification',
            'workflowSteps.workflowStep.role',
            'workflowSteps.assignedUser',
            'workflowSteps.history.actionUser',
        ])->find($requestId);

        if (!$request) {
            throw new RuntimeException("Solicitud {$requestId} no encontrada.");
        }

        // customerId stores the external idCliente directly
        $externalClientId = $request->customerId;

        $customerName = $externalClientId
            ? $this->resolveCustomerName((int) $externalClientId)
            : '';

        // Local Customer row (for salesEngineer relationship)
        $localCustomer = $externalClientId
            ? Customer::with('salesEngineer')
                ->where('idClient', $externalClientId)
                ->first()
            : null;

        $approvals = $this->resolveApprovals($request);

        $data = [
            'request'          => $request,
            'externalClientId' => $externalClientId,
            'customerName'     => $customerName,
            'localCustomer'    => $localCustomer,
            'auditor'          => $approvals['auditor'],
            'managers'         => $approvals['managers'],
            'finance'          => $approvals['finance'],
        ];

        $pdf = Pdf::loadView('requests.credit-request-form', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream("solicitud-{$request->requestNumber}.pdf");
    }

    private function resolveCustomerName(int $idCliente): string
    {
        try {
            $hasTable = Schema::connection(self::INVOICES_CONNECTION)
                ->hasTable(self::CLIENT_TABLE);

            if (!$hasTable) {
                return '';
            }

            $row = DB::connection(self::INVOICES_CONNECTION)
                ->table(self::CLIENT_TABLE)
                ->where('idCliente', $idCliente)
                ->select(['razonSocial'])
                ->first();

            return $row ? (string) ($row->razonSocial ?? '') : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private const MANAGER_ROLE_ORDER = [
        'MANAGER',
        'GENERAL MANAGER',
        'FINANCE',
        'BUSINESS CONTROLLER',
        'BUSINESS CONTROLLERS',
        'VICE PRESIDENT LATIN AMERICA',
        'VICE PRESIDENT & CONTROLLER',
    ];

    private function resolveApprovals(Request $request): array
    {
        $auditor  = null;
        $finance  = null;
        // keyed by role name for deduplication, ordered later
        $managersByRole = [];

        foreach ($request->workflowSteps as $requestStep) {
            $roleName = strtoupper(trim((string) ($requestStep->workflowStep?->role?->roleName ?? '')));
            $stepName = strtoupper(trim((string) ($requestStep->workflowStep?->stepName ?? '')));

            $approvedHistory = $requestStep->history
                ->where('actionType', 'approved')
                ->sortByDesc('createdAt')
                ->first();

            if (!$approvedHistory) {
                continue;
            }

            $approverName      = $approvedHistory->actionUser?->fullName ?? '';
            $assignedUserName  = $requestStep->assignedUser?->fullName ?? $approverName;
            $date              = $approvedHistory->createdAt?->format('d/m/Y') ?? '';

            if (
                str_contains($roleName, 'AUDITOR')
                || str_contains($stepName, 'AUDIT')
            ) {
                $auditor = ['name' => $approverName, 'date' => $date];
                continue;
            }

            if (
                str_contains($roleName, 'FINANCE')
                || str_contains($stepName, 'FINANCE')
                || str_contains($stepName, 'FINANZ')
            ) {
                $finance = ['name' => $approverName, 'date' => $date];
                $managersByRole['FINANCE'] = ['role' => 'FINANCE', 'name' => $assignedUserName, 'date' => $date];
                continue;
            }

            if (str_contains($roleName, 'BUSINESS CONTROLLER')) {
                $managersByRole['BUSINESS CONTROLLER'] = ['role' => 'BUSINESS CONTROLLER', 'name' => $assignedUserName, 'date' => $date];
                continue;
            }

            foreach (self::MANAGER_ROLE_ORDER as $knownRole) {
                if ($roleName === $knownRole) {
                    $managersByRole[$knownRole] = ['role' => $knownRole, 'name' => $assignedUserName, 'date' => $date];
                    break;
                }
            }
        }

        // Sort managers by the defined order
        $managers = [];
        foreach (self::MANAGER_ROLE_ORDER as $knownRole) {
            if (isset($managersByRole[$knownRole])) {
                $managers[] = $managersByRole[$knownRole];
            }
        }

        return compact('auditor', 'managers', 'finance');
    }
}
