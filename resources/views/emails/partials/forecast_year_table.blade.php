{{--
    Panorama de los 12 meses del año. Los meses tocados en la operación se resaltan
    en ámbar y muestran el monto anterior tachado junto al nuevo.

    Espera: $overview (ver ForecastYearOverviewService::build()).
    Opcionales, según el correo que lo incluya:
      - $overviewCaption: qué son los meses resaltados.
      - $changedBadge:    texto de la etiqueta de cada mes resaltado.
--}}
@if (!empty($overview) && !empty($overview['months']))
    @php
        $caption = $overviewCaption ?? 'los meses resaltados son los que cambian';
        $badge   = $changedBadge ?? 'Modificado';
    @endphp

    <p style="margin: 0 0 10px; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
        Objetivo de ventas {{ $overview['year'] }} — {{ $caption }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0"
        style="border-collapse: collapse; margin: 0 0 30px; border: 1px solid #e2e8f0;">
        <tr style="background-color: #f7fafc;">
            <th align="left" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Mes</th>
            <th align="right" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Objetivo</th>
            <th align="left" style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">&nbsp;</th>
        </tr>

        @foreach ($overview['months'] as $row)
            <tr style="background-color: {{ $row['changed'] ? '#fffaf0' : '#ffffff' }};">
                <td style="padding: 10px 12px; border-bottom: 1px solid #edf2f7; border-left: 3px solid {{ $row['changed'] ? '#dd6b20' : 'transparent' }}; color: {{ $row['changed'] ? '#7b341e' : '#4a5568' }}; font-size: 14px; font-weight: {{ $row['changed'] ? '700' : '400' }};">
                    {{ $row['monthLabel'] }}
                </td>
                <td align="right" style="padding: 10px 12px; border-bottom: 1px solid #edf2f7; color: {{ $row['changed'] ? '#dd6b20' : '#4a5568' }}; font-size: {{ $row['changed'] ? '15px' : '14px' }}; font-weight: {{ $row['changed'] ? '700' : '400' }};">
                    @if ($row['changed'] && $row['previousAmount'] !== null)
                        <span style="color: #a0aec0; font-size: 12px; text-decoration: line-through;">${{ number_format((float) $row['previousAmount'], 2) }}</span>
                        <span style="color: #a0aec0; font-size: 12px;">&nbsp;&rarr;&nbsp;</span>
                    @endif
                    ${{ number_format((float) $row['amount'], 2) }}
                </td>
                <td style="padding: 10px 12px; border-bottom: 1px solid #edf2f7;">
                    @if ($row['changed'])
                        <span style="display: inline-block; background-color: #dd6b20; color: #ffffff; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; padding: 3px 8px; border-radius: 10px;">{{ $badge }}</span>
                    @endif
                </td>
            </tr>
        @endforeach

        <tr style="background-color: #f7fafc;">
            <td style="padding: 12px; border-top: 2px solid #e2e8f0; color: #2d3748; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Total del año</td>
            <td align="right" style="padding: 12px; border-top: 2px solid #e2e8f0; color: {{ !empty($overview['exceedsTarget']) ? '#c53030' : '#2d3748' }}; font-size: 16px; font-weight: 700;">
                ${{ number_format((float) $overview['total'], 2) }}
            </td>
            <td style="padding: 12px; border-top: 2px solid #e2e8f0;">&nbsp;</td>
        </tr>

        @if (!empty($overview['annualTarget']))
            <tr style="background-color: #f7fafc;">
                <td style="padding: 4px 12px 12px; color: #718096; font-size: 12px;">Objetivo anual</td>
                <td align="right" style="padding: 4px 12px 12px; color: #718096; font-size: 13px; font-weight: 600;">
                    ${{ number_format((float) $overview['annualTarget'], 2) }}
                </td>
                <td style="padding: 4px 12px 12px;">&nbsp;</td>
            </tr>
        @endif
    </table>

    @if (!empty($overview['exceedsTarget']))
        <p style="margin: -18px 0 26px; padding: 10px 12px; background-color: #fff5f5; border-left: 3px solid #c53030; color: #742a2a; font-size: 13px; line-height: 1.5;">
            La suma de los 12 meses supera el objetivo anual. Hay que reajustar los meses.
        </p>
    @endif
@endif
