<?php

namespace App\Console\Commands;

use App\Mail\ReturnsPolicyReminderMail;
use App\Services\EmailSenderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recuerda a los clientes, un mes antes, que su ventana de devoluciones
 * (definida por el último dígito de su número de cliente) está por vencer.
 *
 * Mapa dígito → mes de vencimiento:
 *  1 Ene, 2 Feb, 3 Mar, 4 Abr, 5 May, 6 Jun, 7 Jul, 8 Ago, 9 Sep, 0 Oct
 *
 * Se ejecuta el día 1 de cada mes: revisa qué dígito vence el mes SIGUIENTE
 * y notifica solo a los clientes de ese dígito que tengan al menos un
 * correo registrado en `correosForecast` (columna reutilizada; sin uso previo).
 *
 * Opcionalmente recibe un dígito (`reminders:returns-policy 3`) para notificar a
 * los clientes de ese dígito sin importar el mes en curso.
 */
class SendReturnsPolicyReminders extends Command
{
    private const CONNECTION       = 'invoices';
    private const CLIENT_TABLE     = 'clientes_TME700618RC7';
    private const CLIENT_EXT_TABLE = 'clientes_TME700618RC7_ext';

    /** Mes de vencimiento => último dígito del número de cliente. */
    private const MONTH_TO_DIGIT = [
        1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5,
        6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 0,
    ];

    private const MONTH_NAMES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    protected $signature = 'reminders:returns-policy {digit? : Último dígito del número de cliente (0-9). Si se omite, se usa el que vence el mes siguiente}';

    protected $description = 'Envía el recordatorio anual de política de devoluciones, un mes antes del vencimiento del último dígito del cliente';

    public function __construct(private readonly EmailSenderService $emailSender)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $digitArgument = $this->argument('digit');

        if ($digitArgument !== null) {
            if (!preg_match('/^[0-9]$/', (string) $digitArgument)) {
                $this->error("El dígito '{$digitArgument}' no es válido. Use un solo dígito de 0 a 9.");
                return Command::FAILURE;
            }

            $digit = (int) $digitArgument;
            $deadlineMonth = array_search($digit, self::MONTH_TO_DIGIT, true);
        } else {
            $deadlineMonth = now()->addMonthNoOverflow()->month;
            $digit = self::MONTH_TO_DIGIT[$deadlineMonth] ?? null;

            if ($digit === null) {
                $this->info("El mes de vencimiento ({$deadlineMonth}) no tiene dígito asociado. No se envían recordatorios.");
                return Command::SUCCESS;
            }
        }

        if (!Schema::connection(self::CONNECTION)->hasColumn(self::CLIENT_EXT_TABLE, 'correosForecast')) {
            $this->warn('La columna correosForecast no existe todavía. Nada que enviar.');
            return Command::SUCCESS;
        }

        $clients = DB::connection(self::CONNECTION)
            ->table(self::CLIENT_TABLE . ' as cl')
            ->join(self::CLIENT_EXT_TABLE . ' as cle', 'cle.idCliente', '=', 'cl.idCliente')
            ->whereRaw('RIGHT(cl.idCliente, 1) = ?', [(string) $digit])
            ->whereNotNull('cle.correosForecast')
            ->where('cle.correosForecast', '!=', '')
            ->select(['cl.idCliente', 'cl.razonSocial', 'cle.correosForecast'])
            ->get();

        if ($clients->isEmpty()) {
            $this->info("No hay clientes con correos registrados para el dígito {$digit}.");
            return Command::SUCCESS;
        }

        $monthName = self::MONTH_NAMES[$deadlineMonth];
        $sent = 0;

        foreach ($clients as $client) {
            $emails = array_values(array_filter(array_map('trim', explode(';', (string) $client->correosForecast))));

            if (empty($emails)) {
                continue;
            }

            $this->emailSender->sendWithCopies(
                new ReturnsPolicyReminderMail(
                    (string) $client->idCliente,
                    (string) $client->razonSocial,
                    $monthName,
                    $digit,
                ),
                $emails,
            );

            $sent++;
        }

        $this->info("Returns policy reminders sent: {$sent} (digit {$digit}, deadline month {$monthName})");

        return Command::SUCCESS;
    }
}
