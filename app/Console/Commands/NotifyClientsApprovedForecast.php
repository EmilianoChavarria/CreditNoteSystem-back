<?php

namespace App\Console\Commands;

use App\Mail\ForecastFinalApprovedSummaryMail;
use App\Models\Distributor;
use App\Models\DistributorForecastChangeRequest;
use App\Models\ForecastChangeRequest;
use App\Services\EmailSenderService;
use App\Services\ForecastApprovalService;
use App\Services\ForecastRoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aviso diario al cliente de los cambios de objetivo ya aprobados. Se manda un
 * solo correo por cliente con todos sus meses pendientes de avisar, sin importar
 * si la aprobación fue por el flujo normal o directa (FORECAST ADMIN / SALES
 * MANAGER). Cada solicitud avisada queda marcada con clientNotifiedAt.
 */
class NotifyClientsApprovedForecast extends Command
{
    protected $signature = 'forecast:notify-approved-clients {--dry-run : Muestra lo que se enviaría sin mandar correos}';

    protected $description = 'Envía a cada cliente un resumen de los cambios de forecast aprobados que aún no se le han notificado';

    public function __construct(
        private readonly EmailSenderService $emailSender,
        private readonly ForecastApprovalService $approvalService,
        private readonly ForecastRoleService $roleService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent   = $this->notifyClients($dryRun) + $this->notifyDistributors($dryRun);

        $this->info($dryRun
            ? "Simulación terminada: {$sent} correo(s) se habrían enviado."
            : "Proceso terminado: {$sent} correo(s) enviado(s).");

        return Command::SUCCESS;
    }

    /** Clientes nacionales y grupos (forecastchangerequests). */
    private function notifyClients(bool $dryRun): int
    {
        $pending = ForecastChangeRequest::query()
            ->where('status', 'approved')
            ->whereNull('clientNotifiedAt')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        if ($pending->isEmpty()) {
            $this->line('Clientes: no hay aprobaciones pendientes de avisar.');
            return 0;
        }

        $clientNames = $this->approvalService->getClientNames(
            $pending->pluck('idClient')->unique()->map(fn($id) => (int) $id)->all()
        );

        $forecastAdmin = $this->roleService->findForecastAdmin();
        $sent          = 0;

        foreach ($pending->groupBy('idClient') as $idClient => $requests) {
            $idClient   = (int) $idClient;
            $clientName = $clientNames[$idClient] ?? (string) $idClient;
            $emails     = $this->approvalService->getClientEmails($idClient);

            $changes = $requests->map(fn(ForecastChangeRequest $r) => [
                'monthLabel'     => ForecastApprovalService::monthLabel((int) $r->month, (int) $r->year),
                'previousAmount' => (float) $r->previousAmount,
                'proposedAmount' => (float) $r->proposedAmount,
            ])->values()->all();

            $bcc = array_values(array_filter([(string) ($forecastAdmin?->email ?? '')]));

            if ($this->deliver($clientName, $changes, $emails, $bcc, $requests, $dryRun)) {
                $sent++;
            }
        }

        return $sent;
    }

    /** Clientes extranjeros (distributorforecastchangerequests). */
    private function notifyDistributors(bool $dryRun): int
    {
        $pending = DistributorForecastChangeRequest::query()
            ->where('status', 'approved')
            ->whereNull('clientNotifiedAt')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        if ($pending->isEmpty()) {
            $this->line('Distribuidores: no hay aprobaciones pendientes de avisar.');
            return 0;
        }

        $distributors = Distributor::whereIn('id', $pending->pluck('distributorId')->unique()->all())
            ->get()
            ->keyBy('id');

        $forecastAdmin = $this->roleService->findForecastAdmin();
        $sent          = 0;

        foreach ($pending->groupBy('distributorId') as $distributorId => $requests) {
            $distributor = $distributors->get((int) $distributorId);
            $clientName  = (string) ($distributor?->businessName ?? $distributorId);

            $emails = $distributor?->emails
                ? array_values(array_filter(array_map('trim', explode(',', (string) $distributor->emails))))
                : [];

            $changes = $requests->map(fn(DistributorForecastChangeRequest $r) => [
                'monthLabel'     => ForecastApprovalService::monthLabel((int) $r->month, (int) $r->year),
                'previousAmount' => (float) $r->previousForecast,
                'proposedAmount' => (float) $r->proposedForecast,
            ])->values()->all();

            $bcc = array_values(array_filter([
                (string) ($distributor?->salesManager?->email ?? ''),
                (string) ($forecastAdmin?->email ?? ''),
            ]));

            if ($this->deliver($clientName, $changes, $emails, $bcc, $requests, $dryRun)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Manda el resumen y marca las solicitudes como avisadas. Un cliente sin
     * correos registrados se marca igual, para no reintentarlo cada noche.
     *
     * @param array<int, array{monthLabel:string, previousAmount:float, proposedAmount:float}> $changes
     * @param string[]                                                                        $emails
     * @param string[]                                                                        $bcc
     * @param Collection<int, ForecastChangeRequest|DistributorForecastChangeRequest>         $requests
     */
    private function deliver(string $clientName, array $changes, array $emails, array $bcc, Collection $requests, bool $dryRun): bool
    {
        $months = count($changes);

        if (empty($emails)) {
            $this->warn("· {$clientName}: {$months} mes(es) aprobado(s) pero no tiene correos registrados; se marca como avisado.");
            Log::warning('Forecast aprobado sin correos de cliente', [
                'client' => $clientName,
                'months' => $months,
            ]);

            if (!$dryRun) {
                $this->markNotified($requests);
            }

            return false;
        }

        $this->line("· {$clientName}: {$months} mes(es) → " . implode(', ', $emails));

        if ($dryRun) {
            return true;
        }

        try {
            $this->emailSender->sendWithCopies(
                new ForecastFinalApprovedSummaryMail(clientName: $clientName, changes: $changes),
                $emails,
                bcc: $bcc,
            );
        } catch (Throwable $e) {
            // Sin marcar: el próximo corrida lo vuelve a intentar.
            $this->error("  Falló el envío a {$clientName}: {$e->getMessage()}");
            Log::error('Error avisando al cliente el forecast aprobado', [
                'client' => $clientName,
                'error'  => $e->getMessage(),
            ]);

            return false;
        }

        $this->markNotified($requests);

        return true;
    }

    /** @param Collection<int, ForecastChangeRequest|DistributorForecastChangeRequest> $requests */
    private function markNotified(Collection $requests): void
    {
        $now = now();

        foreach ($requests as $request) {
            $request->forceFill(['clientNotifiedAt' => $now])->save();
        }
    }
}
