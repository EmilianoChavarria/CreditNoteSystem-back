<?php

namespace App\Console\Commands;

use App\Mail\ForecastApprovalReminderMail;
use App\Models\ForecastChangeRequest;
use App\Services\EmailSenderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendForecastApprovalReminders extends Command
{
    private const EXT_CONNECTION = 'invoices';
    private const CLIENT_TABLE   = 'clientes_TME700618RC7';
    private const INACTIVITY_HOURS = 48;

    protected $signature = 'reminders:forecast-pending-approvals';

    protected $description = 'Send daily reminder emails for forecast requests pending approval after 48 hours of inactivity';

    public function __construct(private readonly EmailSenderService $emailSender)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $staleRequests = ForecastChangeRequest::query()
            ->where('status', 'pending')
            ->where('updatedAt', '<=', now()->subHours(self::INACTIVITY_HOURS))
            ->with('approver')
            ->get();

        if ($staleRequests->isEmpty()) {
            $this->info('No hay solicitudes de forecast con más de 48 horas de inactividad.');
            return Command::SUCCESS;
        }

        $clientNames = $this->resolveClientNames($staleRequests->pluck('idClient')->unique()->values()->all());

        // Un recordatorio por aprobador y cliente: la aprobación es todo o nada
        // por cliente, así que el correo también sale por cliente.
        $grouped = $staleRequests->groupBy(fn (ForecastChangeRequest $r) => $r->approverUserId . '|' . $r->idClient);

        $sent = 0;

        foreach ($grouped as $requests) {
            $approver = $requests->first()->approver;

            if (!$approver || empty($approver->email)) {
                continue;
            }

            $items = $requests->map(fn (ForecastChangeRequest $r) => [
                'clientId'       => (int) $r->idClient,
                'clientName'     => $clientNames[(int) $r->idClient] ?? '',
                'month'          => (int) $r->month,
                'year'           => (int) $r->year,
                'proposedAmount' => (string) $r->proposedAmount,
                'daysPending'    => (int) $r->updatedAt->diffInDays(now()),
            ])->values()->toArray();

            $this->emailSender->send(
                new ForecastApprovalReminderMail(
                    (string) $approver->fullName,
                    $items,
                    (string) ($items[0]['clientName'] ?? '')
                ),
                (string) $approver->email
            );

            $sent++;
        }

        $this->info("Reminder emails sent: {$sent}");

        return Command::SUCCESS;
    }

    /**
     * @param array<int, int> $clientIds
     * @return array<int, string>
     */
    private function resolveClientNames(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        return DB::connection(self::EXT_CONNECTION)
            ->table(self::CLIENT_TABLE)
            ->whereIn('idCliente', $clientIds)
            ->pluck('razonSocial', 'idCliente')
            ->all();
    }
}
