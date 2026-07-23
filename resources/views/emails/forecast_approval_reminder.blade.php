<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forecasts pendientes de aprobación</title>
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
                                Tienes solicitudes de cambio de objetivo de ventas (forecast) que llevan más de 48 horas esperando tu aprobación.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background-color: #EDEDED; border-radius: 6px; margin: 0 0 30px;">
                                <tr>
                                    <td style="padding: 16px 24px; border-bottom: 1px solid #d1d5db;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="40%" style="color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Cliente</td>
                                                <td width="20%" style="color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Período</td>
                                                <td width="20%" style="color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Monto</td>
                                                <td width="20%" style="color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Días en espera</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @foreach ($items as $item)
                                <tr>
                                    <td style="padding: 14px 24px; {{ !$loop->last ? 'border-bottom: 1px solid #d1d5db;' : '' }}">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="40%" style="color: #2d3748; font-size: 14px; font-weight: 700;">
                                                    #{{ $item['clientId'] }} — {{ $item['clientName'] }}
                                                </td>
                                                <td width="20%" style="color: #2d3748; font-size: 14px;">
                                                    {{ $item['month'] }}/{{ $item['year'] }}
                                                </td>
                                                <td width="20%" style="color: #ff8200; font-size: 14px; font-weight: 700;">
                                                    ${{ number_format((float) $item['proposedAmount'], 2) }}
                                                </td>
                                                <td width="20%" style="color: #e53e3e; font-size: 14px; font-weight: 700;">
                                                    {{ $item['daysPending'] }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @endforeach
                            </table>

                            <p style="margin: 0; color: #4a5568; font-size: 14px; line-height: 1.6;">
                                Ingresa al sistema para aprobar o rechazar estas solicitudes.
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
