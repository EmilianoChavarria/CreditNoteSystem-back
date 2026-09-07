<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forecast pendiente de aprobación</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f7; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">

                    <tr>
                        <td style="padding: 40px 30px; text-align: center;">
                            <img src="{{ $message->embed(public_path('images/LTM.png')) }}" alt="logo"
                                style="max-width: 220px; height: auto; display: block; margin: 0 auto;">
                        </td>
                    </tr>

                    <tr>
                        <td style="border-top: 2px solid #ff8200; font-size: 0; line-height: 0; height: 0;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 20px; color: #2d3748; font-size: 16px; line-height: 1.6;">
                                <strong>Hola, {{ $approverName }}</strong>
                            </p>

                            <p style="margin: 0 0 30px; color: #4a5568; font-size: 15px; line-height: 1.6;">
                                <strong>{{ $submitterName }}</strong> ha enviado
                                {{ count($changes) === 1 ? 'una solicitud de cambio' : count($changes) . ' solicitudes de cambio' }}
                                de objetivo de ventas (forecast) que
                                {{ count($changes) === 1 ? 'requiere' : 'requieren' }} tu aprobación.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background-color: #EDEDED; border-radius: 6px; margin: 0 0 24px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <p style="margin: 0; color: #718096; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Cliente</p>
                                        <p style="margin: 8px 0 16px; color: #2d3748; font-size: 16px; font-weight: 700;">#{{ $clientId }} — {{ $clientName }}</p>

                                        <p style="margin: 0; color: #718096; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Año</p>
                                        <p style="margin: 8px 0 0; color: #2d3748; font-size: 16px;">{{ $year }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 12px; color: #718096; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">
                                Detalle de los cambios propuestos
                            </p>

                            @php
                                $totalPrevious = array_sum(array_map(fn ($c) => (float) $c['previousAmount'], $changes));
                                $totalProposed = array_sum(array_map(fn ($c) => (float) $c['proposedAmount'], $changes));
                                $totalDelta    = $totalProposed - $totalPrevious;
                                $totalColor    = $totalDelta > 0 ? '#2f855a' : ($totalDelta < 0 ? '#c53030' : '#718096');
                            @endphp

                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="border-collapse: collapse; margin: 0 0 30px; border: 1px solid #e2e8f0;">
                                <tr style="background-color: #f7fafc;">
                                    <th align="left" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Mes</th>
                                    <th align="right" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Objetivo actual</th>
                                    <th align="right" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Monto propuesto</th>
                                    <th align="right" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Diferencia</th>
                                </tr>
                                @foreach ($changes as $change)
                                    @php
                                        $previous   = (float) $change['previousAmount'];
                                        $proposed   = (float) $change['proposedAmount'];
                                        $delta      = $proposed - $previous;
                                        $deltaColor = $delta > 0 ? '#2f855a' : ($delta < 0 ? '#c53030' : '#718096');
                                    @endphp
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #edf2f7; color: #2d3748; font-size: 14px; font-weight: 600;">{{ $change['monthLabel'] }}</td>
                                        <td align="right" style="padding: 12px; border-bottom: 1px solid #edf2f7; color: #4a5568; font-size: 14px;">${{ number_format($previous, 2) }}</td>
                                        <td align="right" style="padding: 12px; border-bottom: 1px solid #edf2f7; color: #ff8200; font-size: 15px; font-weight: 700;">${{ number_format($proposed, 2) }}</td>
                                        <td align="right" style="padding: 12px; border-bottom: 1px solid #edf2f7; color: {{ $deltaColor }}; font-size: 14px; font-weight: 600;">{{ $delta > 0 ? '+' : '' }}${{ number_format($delta, 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr style="background-color: #f7fafc;">
                                    <td style="padding: 12px; color: #2d3748; font-size: 14px; font-weight: 700;">Total</td>
                                    <td align="right" style="padding: 12px; color: #4a5568; font-size: 14px; font-weight: 700;">${{ number_format($totalPrevious, 2) }}</td>
                                    <td align="right" style="padding: 12px; color: #ff8200; font-size: 15px; font-weight: 700;">${{ number_format($totalProposed, 2) }}</td>
                                    <td align="right" style="padding: 12px; color: {{ $totalColor }}; font-size: 14px; font-weight: 700;">{{ $totalDelta > 0 ? '+' : '' }}${{ number_format($totalDelta, 2) }}</td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 20px; color: #4a5568; font-size: 14px; line-height: 1.6;">
                                Cada mes se aprueba o rechaza de forma individual dentro del sistema.
                            </p>

                            <p style="margin: 0; color: #4a5568; font-size: 14px; line-height: 1.6;">
                                Ingresa a la plataforma para revisar y aprobar las solicitudes:
                                <a href="https://timken.ittec.mx/" style="color: #ff8200; text-decoration: none;">https://timken.ittec.mx/</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #ff8200; padding: 30px; text-align: center;">
                            <p style="margin: 0; color: #FFFFFF; font-size: 13px; line-height: 1.5;">
                                Este correo fue generado automáticamente. Por favor no responder.
                            </p>
                            @if (!empty($isOverride) && !empty($originalRecipient))
                            <p style="margin: 12px 0 0; color: #FFFFFF; font-size: 12px; line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.4); padding-top: 12px;">
                                [Override] Destinatario original: {{ $originalRecipient }}
                            </p>
                            @endif
                            <p style="margin: 16px 0 0; color: #FFFFFF; font-size: 13px;">
                                © {{ now()->year }} <span style="text-decoration: underline;">ITTEC. Tecnología Inteligente.</span> Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
