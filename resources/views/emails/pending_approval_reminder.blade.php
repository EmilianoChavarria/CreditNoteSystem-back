<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('emails.reminder_title') }}</title>
</head>

<body
    style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f7; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 30px; text-align: center;">
                            <img src="{{ $message->embed(public_path('images/LTM.png')) }}" alt="logo_timken"
                                style="max-width: 220px; height: auto; display: block; margin: 0 auto;">
                        </td>
                    </tr>

                    <tr>
                        <td style="border-top: 2px solid #ff8200; font-size: 0; line-height: 0; height: 0;">&nbsp;</td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 20px; color: #2d3748; font-size: 16px; line-height: 1.6;">
                                <strong>{{ __('emails.reminder_greeting', ['name' => $fullName]) }}</strong>
                            </p>

                            <p style="margin: 0 0 30px; color: #4a5568; font-size: 15px; line-height: 1.6;">
                                {{ __('emails.reminder_intro') }}
                            </p>

                            <!-- Requests Table -->
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background-color: #EDEDED; border-radius: 6px; margin: 0 0 30px;">
                                <tr>
                                    <td style="padding: 16px 24px; border-bottom: 1px solid #d1d5db;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="40%" style="color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">
                                                    {{ __('emails.reminder_number_label') }}
                                                </td>
                                                <td width="30%" style="color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">
                                                    {{ __('emails.reminder_type_label') }}
                                                </td>
                                                <td width="30%" style="color: #718096; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">
                                                    {{ __('emails.reminder_class_label') }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @foreach ($requests as $req)
                                <tr>
                                    <td style="padding: 14px 24px; {{ !$loop->last ? 'border-bottom: 1px solid #d1d5db;' : '' }}">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="40%" style="color: #2d3748; font-size: 14px; font-weight: 700;">
                                                    {{ $req['requestNumber'] }}
                                                </td>
                                                <td width="30%" style="color: #2d3748; font-size: 14px;">
                                                    {{ Str::title(mb_strtolower($req['requestType'])) }}
                                                </td>
                                                <td width="30%" style="color: #2d3748; font-size: 14px;">
                                                    {{ $req['classification'] }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @endforeach
                            </table>

                            <p style="margin: 0; color: #4a5568; font-size: 14px; line-height: 1.6;">
                                {{ __('emails.reminder_cta') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #ff8200; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0; color: #FFFFFF; font-size: 13px; line-height: 1.5;">
                                {{ __('emails.reminder_footer') }}
                            </p>
                            @if (!empty($isOverride) && !empty($originalRecipient))
                            <p style="margin: 12px 0 0; color: #FFFFFF; font-size: 12px; line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.4); padding-top: 12px;">
                                {{ __('emails.reminder_override_notice', ['recipient' => $originalRecipient]) }}
                            </p>
                            @endif
                            <p style="margin: 16px 0 0; color: #FFFFFF; font-size: 13px;">
                                © {{ now()->year }}
                                <span style="text-decoration: underline;">ITTEC. Tecnología Inteligente.</span> {{ __('emails.footer_rights') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>
