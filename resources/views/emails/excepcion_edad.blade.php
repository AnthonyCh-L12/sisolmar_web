<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excepción de Edad - Grupo Solar</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f0f2f5; font-family: 'Segoe UI', Arial, sans-serif;">

    <!-- Contenido -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f0f2f5; padding-bottom: 40px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-bottom: 6px solid #34A1E5;">

                    <!-- Cabecera con logo -->
                    <tr>
                        <td style="padding: 20px 30px; border-bottom: 2px solid #34A1E5;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left">
                                        <img src="{{ $message->embed(public_path('images/grupo_solar_email.png')) }}" alt="GRUPO SOLAR" style="width: 300px; max-width: 100%; height: auto;">
                                    </td>
                                    <td align="right" style="font-size: 11px; color: #94a3b8; letter-spacing: 1px; font-weight: 600;">
                                        SISTEMA DE RECURSOS HUMANOS
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Icono + Titulo -->
                    <tr>
                        <td align="center" style="padding: 40px 40px 25px 40px;">
                            <div style="display: inline-block; width: 64px; height: 64px; background-color: #34A1E5; border-radius: 50%; text-align: center; line-height: 64px;">
                                <span style="color: #ffffff; font-size: 30px; font-weight: bold;">!</span>
                            </div>
                            <h2 style="margin: 20px 0 0 0; color: #003366; font-size: 20px; font-weight: 900; letter-spacing: 0.5px;">EXCEPCIÓN DE EDAD REGISTRADA</h2>
                            <p style="margin: 12px 0 0 0; color: #666666; font-size: 13px; line-height: 1.6;">
                                Se ha registrado una <strong>excepción de edad</strong> en la Declaración Jurada<br>de un colaborador. La edad permitida es entre <strong>{{ $datosCorreo['edad_minima'] }}</strong> y <strong>{{ $datosCorreo['edad_maxima'] }}</strong> años.
                            </p>
                        </td>
                    </tr>

                    <!-- Tabla de Detalles -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                                <!-- Header -->
                                <tr>
                                    <td colspan="4" style="background-color: #004b87; color: #ffffff; padding: 12px 20px; font-size: 12px; font-weight: bold; letter-spacing: 1px;">
                                        DETALLES DE LA EXCEPCIÓN
                                    </td>
                                </tr>
                                <!-- Fila 1 -->
                                <tr>
                                    <td width="18%" style="padding: 14px 16px; background-color: #f8fafc; font-size: 12px; color: #64748b; font-weight: 600; border-bottom: 1px solid #cbd5e1;">NOMBRE:</td>
                                    <td colspan="3" style="padding: 14px 16px; font-size: 13px; color: #1e293b; font-weight: 700; border-bottom: 1px solid #cbd5e1;">{{ $datosCorreo['nombre'] }}</td>
                                </tr>
                                <!-- Separador -->
                                <tr><td colspan="4" style="border-bottom: 1px solid #cbd5e1; font-size: 0; line-height: 0;">&nbsp;</td></tr>
                                <!-- Fila 2 -->
                                <tr>
                                    <td style="padding: 14px 16px; background-color: #f8fafc; font-size: 12px; color: #64748b; font-weight: 600; border-bottom: 1px solid #cbd5e1;">DNI:</td>
                                    <td style="padding: 14px 16px; font-size: 13px; color: #1e293b; font-weight: 700; border-bottom: 1px solid #cbd5e1;">{{ $datosCorreo['dni'] }}</td>
                                     <td style="padding: 14px 16px; background-color: #f8fafc; font-size: 12px; color: #64748b; font-weight: 600; border-bottom: 1px solid #cbd5e1;">TIPO TRABAJADOR:</td>
                                     <td style="padding: 14px 16px; font-size: 13px; color: #1e293b; font-weight: 700; border-bottom: 1px solid #cbd5e1;">{{ $datosCorreo['tipo_trabajador'] ?? 'N/A' }}</td>
                                </tr>
                                <!-- Separador -->
                                <tr><td colspan="4" style="border-bottom: 1px solid #cbd5e1; font-size: 0; line-height: 0;">&nbsp;</td></tr>
                                <!-- Fila 3 -->
                                <tr>
                                    <td style="padding: 14px 16px; background-color: #f8fafc; font-size: 12px; color: #64748b; font-weight: 600; border-bottom: 1px solid #cbd5e1;">FECHA NAC.:</td>
                                    <td style="padding: 14px 16px; font-size: 13px; color: #1e293b; font-weight: 700; border-bottom: 1px solid #cbd5e1;">{{ \Carbon\Carbon::parse($datosCorreo['fecha_nacimiento'])->format('d/m/Y') }}</td>
                                     <td style="padding: 14px 16px; background-color: #f8fafc; font-size: 12px; color: #64748b; font-weight: 600; border-bottom: 1px solid #cbd5e1;">SUCURSAL:</td>
                                     <td style="padding: 14px 16px; font-size: 13px; color: #1e293b; font-weight: 700; border-bottom: 1px solid #cbd5e1;">{{ $datosCorreo['sucursal'] ?? 'N/A' }}</td>
                                </tr>
                                <!-- Separador -->
                                <tr><td colspan="4" style="border-bottom: 1px solid #cbd5e1; font-size: 0; line-height: 0;">&nbsp;</td></tr>
                                <!-- Fila 4 -->
                                <tr>
                                    <td style="padding: 14px 16px; background-color: #f8fafc; font-size: 12px; color: #64748b; font-weight: 600; border-bottom: 1px solid #cbd5e1;">EDAD CALC.:</td>
                                    <td style="padding: 14px 16px; font-size: 15px; color: {{ $datosCorreo['tipo_excepcion'] === 'MINIMA' ? '#dc2626' : '#34A1E5' }}; font-weight: 900; border-bottom: 1px solid #cbd5e1;">{{ $datosCorreo['edad'] }} años</td>
                                    <td style="padding: 14px 16px; background-color: #f8fafc; font-size: 12px; color: #64748b; font-weight: 600; border-bottom: 1px solid #cbd5e1;">AUTORIZADO POR:</td>
                                    <td style="padding: 14px 16px; font-size: 13px; color: #1e293b; font-weight: 700; border-bottom: 1px solid #cbd5e1;">{{ $datosCorreo['usuario_autorizador'] }}</td>
                                </tr>
                                <!-- Separador -->
                                <tr><td colspan="4" style="border-bottom: 1px solid #cbd5e1; font-size: 0; line-height: 0;">&nbsp;</td></tr>
                                <!-- Fila 5 -->
                                <tr>
                                    <td style="padding: 14px 16px; background-color: #f8fafc; font-size: 12px; color: #64748b; font-weight: 600;">USUARIO REG.:</td>
                                    <td style="padding: 14px 16px; font-size: 13px; color: #1e293b; font-weight: 700;">{{ $datosCorreo['usuario_registro'] }}</td>
                                    <td style="padding: 14px 16px; background-color: #f8fafc; font-size: 12px; color: #64748b; font-weight: 600;">FECHA AUTORIZ.:</td>
                                    <td style="padding: 14px 16px; font-size: 13px; color: #1e293b; font-weight: 700;">{{ \Carbon\Carbon::parse($datosCorreo['fecha_autorizacion'])->format('d/m/Y - H:i') }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

                <!-- Footer -->
                <table width="600" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="center" style="padding: 30px 20px;">
                            <div style="color: #b0b8c0; font-size: 10px; font-weight: bold; margin-bottom: 12px; letter-spacing: 1px;">GRUPO SOLAR</div>
                            <p style="color: #88929c; font-size: 11px; margin: 0; line-height: 1.6;">
                                Este es un mensaje automático generado por el Sistema de Recursos Humanos.<br>
                                Por favor, no responda a esta dirección de correo electrónico.<br>
                                &copy; {{ date('Y') }} GRUPO SOLAR. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
