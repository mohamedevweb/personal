<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f7f5f0;color:#171715;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f7f5f0;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:580px;">
                    <tr>
                        <td align="center" style="padding:0 0 24px;">
                            <a href="{{ rtrim((string) config('app.frontend_url'), '/') }}" style="color:#171715;text-decoration:none;">
                                <img src="{{ asset('images/personal-mark.png') }}" width="24" height="24" alt="" style="display:inline-block;width:24px;height:24px;border:0;vertical-align:-3px;">
                                <span style="font-family:Georgia,'Times New Roman',serif;font-size:27px;letter-spacing:-0.5px;">Personal</span>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #e4dfd4;border-radius:24px;background:#fefdfb;padding:48px 44px;">
                            <h1 style="margin:0;color:#171715;font-family:Georgia,'Times New Roman',serif;font-size:42px;font-weight:400;line-height:1.08;letter-spacing:-1px;">{{ $title }}</h1>
                            <p style="margin:22px 0 0;color:#77736d;font-size:16px;line-height:1.65;">{{ $copy }}</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:30px 0;">
                                <tr>
                                    <td style="border-radius:999px;background:#e04f36;">
                                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:15px 24px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;">{{ $actionLabel }}</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0;color:#8f8a80;font-size:13px;line-height:1.6;">{{ $notice }}</p>
                            <div style="margin-top:30px;padding-top:24px;border-top:1px solid #efebe2;">
                                <p style="margin:0 0 8px;color:#8f8a80;font-size:12px;line-height:1.5;">{{ __('mail.fallback') }}</p>
                                <p style="margin:0;color:#77736d;font-size:12px;line-height:1.5;word-break:break-all;"><a href="{{ $actionUrl }}" style="color:#a8402a;">{{ $actionUrl }}</a></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:24px 16px 0;color:#8f8a80;font-size:12px;line-height:1.6;">
                            {{ __('mail.footer') }}<br>
                            © {{ date('Y') }} Personal
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
