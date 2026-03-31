<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Batch finalizado</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f7; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <tr>
                        <td style="background-color: #1f4e79; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: 700;">
                                Resultado del procesamiento de batch
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 32px 28px;">
                            <p style="margin: 0 0 14px; color: #2d3748; font-size: 16px; line-height: 1.6;">
                                Hola <strong>{{ $fullName }}</strong>,
                            </p>

                            <p style="margin: 0 0 20px; color: #4a5568; font-size: 15px; line-height: 1.6;">
                                El batch <strong>#{{ $batchId }}</strong> (tipo <strong>{{ $batchType }}</strong>)
                                ha finalizado con estado:
                                <strong>{{ $status === 'completed' ? 'Completado' : 'Finalizado con errores' }}</strong>.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
                                <tr>
                                    <td style="padding: 12px 16px; background-color: #f7fafc; color: #4a5568; font-size: 14px;">Total de registros</td>
                                    <td style="padding: 12px 16px; text-align: right; color: #1a202c; font-size: 14px;"><strong>{{ $totalRecords }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; background-color: #ffffff; color: #4a5568; font-size: 14px;">Procesados exitosamente</td>
                                    <td style="padding: 12px 16px; text-align: right; color: #1a202c; font-size: 14px;"><strong>{{ $processedRecords - $errorRecords }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; background-color: #f7fafc; color: #4a5568; font-size: 14px;">Con error</td>
                                    <td style="padding: 12px 16px; text-align: right; color: #c53030; font-size: 14px;"><strong>{{ $errorRecords }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; background-color: #ffffff; color: #4a5568; font-size: 14px;">En procesamiento</td>
                                    <td style="padding: 12px 16px; text-align: right; color: #1a202c; font-size: 14px;"><strong>{{ $processingRecords }}</strong></td>
                                </tr>
                            </table>

                            <p style="margin: 18px 0 0; color: #718096; font-size: 13px; line-height: 1.5;">
                                Este es un correo automático, por favor no responder.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
