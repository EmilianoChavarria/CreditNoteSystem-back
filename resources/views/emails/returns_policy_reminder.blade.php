<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recordatorio de política de devoluciones</title>
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
                                <strong>Estimado distribuidor:</strong>
                            </p>

                            <p style="margin: 0 0 20px; color: #4a5568; font-size: 15px; line-height: 1.6;">
                                Derivado de la política anual de devoluciones, le recordamos que para realizar este trámite deberá hacerlo conforme al último dígito de su número de cliente, el cual puede localizar en sus facturas. La correspondencia entre el último dígito y el mes aplicable se encuentra en el cuadro adjunto.
                            </p>

                            <p style="margin: 0 0 20px; color: #2d3748; font-size: 14px; line-height: 1.6;">
                                Cliente: <strong>#{{ $clientId }}{{ $clientName ? ' — ' . $clientName : '' }}</strong>
                                &nbsp;·&nbsp; Último dígito: <strong>{{ $lastDigit }}</strong>
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background-color: #EDEDED; border-radius: 6px; margin: 0 0 30px;">
                                @php
                                    $rows = [
                                        ['Enero', 1], ['Febrero', 2], ['Marzo', 3], ['Abril', 4], ['Mayo', 5],
                                        ['Junio', 6], ['Julio', 7], ['Agosto', 8], ['Septiembre', 9], ['Octubre', 0],
                                    ];
                                @endphp
                                <tr>
                                    <td style="padding: 12px 24px; border-bottom: 1px solid #d1d5db;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">
                                                    Mes de devolución
                                                </td>
                                                <td align="right" style="color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">
                                                    Último dígito de número de cliente
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @foreach ($rows as $i => [$month, $digit])
                                <tr>
                                    <td style="padding: 12px 24px; {{ $i !== count($rows) - 1 ? 'border-bottom: 1px solid #d1d5db;' : '' }} {{ $digit === $lastDigit ? 'background-color: #ffe8cc;' : '' }}">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="color: #2d3748; font-size: 14px; {{ $digit === $lastDigit ? 'font-weight: 700;' : '' }}">
                                                    {{ $month }}
                                                </td>
                                                <td align="right" style="color: #2d3748; font-size: 14px; {{ $digit === $lastDigit ? 'font-weight: 700;' : '' }}">
                                                    {{ $digit }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @endforeach
                            </table>

                            <p style="margin: 0 0 20px; color: #4a5568; font-size: 14px; line-height: 1.6;">
                                Adicionalmente, le informamos que el portal de devoluciones enviará un correo electrónico de recordatorio un mes antes de la fecha de vencimiento.
                            </p>

                            <p style="margin: 0; color: #4a5568; font-size: 14px; line-height: 1.6;">
                                Para cualquier consulta, por favor comuníquese con su ejecutivo de servicio al cliente.
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
