<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Objetivo de ventas actualizado</title>
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
                                Estimado cliente <strong>{{ $clientName }}</strong>,
                            </p>

                            <p style="margin: 0 0 30px; color: #4a5568; font-size: 15px; line-height: 1.6;">
                                Le informamos que su objetivo de ventas ha sido
                                <strong style="color: #38a169;">actualizado</strong>
                                {{ count($changes) === 1 ? 'para el período indicado a continuación' : 'para los períodos indicados a continuación' }}.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="border-collapse: collapse; margin: 0 0 30px; border: 1px solid #e2e8f0;">
                                <tr style="background-color: #f7fafc;">
                                    <th align="left" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Período</th>
                                    <th align="right" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Objetivo anterior</th>
                                    <th align="right" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Nuevo objetivo</th>
                                </tr>
                                @foreach ($changes as $change)
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #edf2f7; color: #2d3748; font-size: 14px; font-weight: 600;">{{ $change['monthLabel'] }}</td>
                                        <td align="right" style="padding: 12px; border-bottom: 1px solid #edf2f7; color: #4a5568; font-size: 14px;">${{ number_format((float) $change['previousAmount'], 2) }}</td>
                                        <td align="right" style="padding: 12px; border-bottom: 1px solid #edf2f7; color: #38a169; font-size: 16px; font-weight: 700;">${{ number_format((float) $change['proposedAmount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            @include('emails.partials.forecast_year_table', ['overviewCaption' => 'los meses resaltados son los que se actualizaron', 'changedBadge' => 'Actualizado'])

                            <p style="margin: 0 0 20px; color: #4a5568; font-size: 14px; line-height: 1.6;">
                                Si tiene alguna duda al respecto, comuníquese con su ejecutivo de ventas asignado.
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
