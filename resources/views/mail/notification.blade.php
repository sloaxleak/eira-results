<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>EIRA: actualización de tu estudio</title>
</head>
<body style="margin:0;padding:0;background:#FFF8F8;font-family:'M PLUS Rounded 1c',Arial,Helvetica,sans-serif;color:#211A1B;">
    <span style="display:none;max-height:0;overflow:hidden;opacity:0;">Tu código de consulta de EIRA está dentro de este correo.</span>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#FFFFFF;border:1px solid #DCC0C1;border-radius:24px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px 16px;border-bottom:3px solid #E7959B;">
                            <img src="{{ asset('img/eira-logo.png') }}" alt="EIRA" width="160" style="display:block;max-width:160px;height:auto;border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <h1 style="margin:0 0 12px;font-size:22px;line-height:28px;color:#211A1B;">Tenemos una actualización de tu estudio</h1>
                            <p style="margin:0 0 20px;font-size:16px;line-height:24px;color:#564243;">
                                Ya puedes consultar tu documento en el portal de EIRA. Necesitarás el <strong>No. de folio de tu formato</strong> y este código:
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:0 32px 8px;">
                            <div style="display:inline-block;padding:16px 28px;background:#FFF0F2;border:1px solid #DCC0C1;border-radius:16px;font-size:32px;line-height:36px;letter-spacing:8px;font-weight:700;color:#963341;font-family:'Courier New',Courier,monospace;">{{ $code }}</div>
                            <p style="margin:12px 0 0;font-size:13px;line-height:18px;color:#564243;">Es de un solo uso y vence {{ $ttl }} después de recibirlo.</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:24px 32px 8px;">
                            <a href="{{ $portalUrl }}" style="display:inline-block;padding:14px 28px;background:#963341;color:#FFFFFF;text-decoration:none;border-radius:9999px;font-size:15px;font-weight:700;">Ir al portal de consulta</a>
                            <p style="margin:12px 0 0;font-size:12px;line-height:16px;color:#897172;word-break:break-all;">{{ $portalUrl }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px;">
                            <div style="padding:16px;background:#FFF8F8;border-radius:12px;font-size:13px;line-height:20px;color:#564243;">
                                <strong style="color:#211A1B;">Por tu privacidad:</strong> este correo no incluye información médica ni tu folio. No compartas el código.
                                Si no esperabas este mensaje o no tienes tu formato, acude a tu unidad EIRA para recibir apoyo presencial.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 28px;border-top:1px solid #F3E5E7;font-size:12px;line-height:16px;color:#897172;">
                            EIRA A.C. · Mensaje automático, no respondas a este correo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
