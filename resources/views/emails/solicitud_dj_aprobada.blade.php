<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de actualización aprobada</title>
</head>
<body style="margin:0;padding:24px;background:#f3f6f9;font-family:Arial,Helvetica,sans-serif;color:#263445;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border:1px solid #dce4ec;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:28px 24px;background:#ffffff;border-bottom:4px solid #36a0e3;">
                            <img src="{{ $message->embed(public_path('images/grupo_solar_email.png')) }}" alt="GRUPO SOLAR" width="300" style="display:block;width:100%;max-width:300px;height:auto;border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 36px 12px;">
                            <p style="margin:0 0 16px;font-size:16px;">Hola, <strong>{{ $datosCorreo['nombre'] }}</strong>.</p>
                            <h1 style="margin:0 0 16px;color:#1686c9;font-size:22px;line-height:1.35;">Tu solicitud fue aprobada</h1>
                            <p style="margin:0;font-size:15px;line-height:1.7;color:#526273;">
                                Te confirmamos que tu solicitud de actualización de datos de la Declaración Jurada fue aprobada y los cambios fueron aplicados. A continuación puedes revisar el dato que estaba registrado y el dato que solicitaste.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 36px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f8fb;border:1px solid #e1e8ef;border-radius:6px;">
                                <tr>
                                    <td style="padding:16px 18px;font-size:14px;line-height:1.8;">
                                        <strong>Personal:</strong> {{ $datosCorreo['nombre'] }}<br>
                                        <strong>DNI:</strong> {{ $datosCorreo['dni'] }}<br>
                                        <strong>Fecha de aprobación:</strong> {{ $datosCorreo['fecha'] }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 36px 28px;">
                            <h2 style="margin:0 0 12px;color:#263445;font-size:16px;">Detalle de los cambios</h2>
                            @if(!empty($datosCorreo['cambios']))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-size:13px;">
                                    <thead>
                                        <tr style="background:#eaf3fa;color:#334155;">
                                            <th align="left" style="padding:10px;border:1px solid #dce4ec;">Dato</th>
                                            <th align="left" style="padding:10px;border:1px solid #dce4ec;">Registrado antes</th>
                                            <th align="left" style="padding:10px;border:1px solid #dce4ec;">Solicitado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($datosCorreo['cambios'] as $cambio)
                                            <tr>
                                                <td style="padding:10px;border:1px solid #dce4ec;font-weight:bold;vertical-align:top;">{{ $cambio['campo'] }}</td>
                                                <td style="padding:10px;border:1px solid #dce4ec;vertical-align:top;">{!! nl2br(e($cambio['anterior'])) !!}</td>
                                                <td style="padding:10px;border:1px solid #dce4ec;vertical-align:top;">{!! nl2br(e($cambio['solicitado'])) !!}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p style="margin:0;color:#526273;font-size:13px;line-height:1.6;">La revisión no registró diferencias entre los datos anteriores y los datos solicitados.</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 24px;background:#f7f9fb;color:#718096;font-size:12px;line-height:1.6;">
                            Mensaje automático del Sistema de Recursos Humanos.<br>
                            <strong style="color:#1686c9;letter-spacing:1px;">GRUPO SOLAR</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
