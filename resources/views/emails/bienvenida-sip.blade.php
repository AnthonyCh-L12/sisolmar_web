<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido al SIP - Grupo Solar</title>
</head>
<body style="margin:0; padding:0; background-color:#ffffff; font-family:Arial, Helvetica, sans-serif;">

    {{-- Logo Grupo Solar --}}
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;">
        <tr>
            <td align="center" style="padding:28px 24px 18px;">
                <img src="{{ $message->embed(public_path('images/grupo_solar_email.png')) }}"
                     alt="GRUPO SOLAR"
                     width="300" style="display:block; max-width:300px; width:100%; height:auto;">
            </td>
        </tr>
    </table>

    {{-- Contenido principal --}}
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0"
                       style="background-color:#ffffff; border-radius:0 0 10px 10px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">

                    {{-- Saludo --}}
                    <tr>
                        <td style="padding:25px 35px 0 35px;">
                            <p style="margin:0 0 2px 0; color:#1a3c6e; font-size:13px; font-weight:bold;">
                                Sr. {{ $datos['nombre'] }}
                            </p>
                            <p style="margin:0; color:#1a3c6e; font-size:13px;">
                                Presente.-
                            </p>
                        </td>
                    </tr>

                    {{-- Felicidades --}}
                    <tr>
                        <td style="padding:18px 35px 0 35px; text-align:center;">
                            <p style="margin:0; color:#1a3c6e; font-size:15px; font-weight:bold;">
                                ¡Felicidades!!
                            </p>
                        </td>
                    </tr>

                    {{-- Texto de bienvenida --}}
                    <tr>
                        <td style="padding:12px 35px 0 35px; color:#1a3c6e; font-size:12.5px; line-height:1.65; text-align:justify;">
                            <p style="margin:0 0 10px 0;">
                                GRUPO SOLAR te da la bienvenida como usuario preferente de su
                                <strong>EXTRANET</strong>, esperamos que lo disfrutes y sea de mucha utilidad para ti.
                            </p>
                            <p style="margin:0 0 10px 0;">
                                A partir de hoy, desde cualquier acceso a Internet podrás ingresar a nuestra
                                Página Web <a href="https://www.gruposolmar.com.pe/sip" style="color:#1a3c6e; text-decoration:underline; font-weight:bold;">www.gruposolmar.com.pe/sip</a>
                                y utilizar el Usuario y Contraseña que te proporcionamos y accederás a tu
                                <strong>SISTEMA DE INFORMACIÓN PERSONAL</strong>, más conocido como SIP.
                            </p>
                            <p style="margin:0 0 10px 0;">
                                Por tu seguridad, esta contraseña la tendrás que cambiar la primera vez que ingreses.
                                No te preocupes que el procedimiento es muy sencillo.
                                <strong>Tus credenciales iniciales son:</strong>
                            </p>
                        </td>
                    </tr>

                    {{-- Credenciales --}}
                    <tr>
                        <td style="padding:8px 35px 8px 35px; text-align:center; color:#1a3c6e; font-size:13px; font-weight:bold; line-height:1.8;">
                            <p style="margin:0;">Usuario : {{ $datos['dni'] }}</p>
                            <p style="margin:0;">Contraseña: {{ $datos['password'] }}</p>
                        </td>
                    </tr>

                    {{-- Descripción SIP --}}
                    <tr>
                        <td style="padding:8px 35px 0 35px; color:#1a3c6e; font-size:12.5px; line-height:1.65; text-align:justify;">
                            <p style="margin:0 0 10px 0;">
                                Tu <strong>SISTEMA DE INFORMACIÓN PERSONAL</strong>, o sencillamente tu
                                <strong>SIP</strong>, te mantendrá informado de muchos aspectos de interés
                                mutuo, siendo preciso mencionar que la información que encontrará es
                                personal y no es vista por otro usuario, por lo que le sugerimos
                                mantener reserva de las credenciales que te alcanzamos y de las que tú
                                registres en el futuro.
                            </p>
                            <p style="margin:0 0 10px 0;">
                                Es muy importante para tu empresa que estés bien informado y es por eso
                                que el SIP te proporcionará virtualmente toda la información que requieras,
                                siendo las principales opciones: Tu Legajo Personal, Boletas de
                                Remuneraciones, capacitaciones, acciones destacadas, beneficios, buzón de
                                sugerencias, quejas y reclamos, así como información de interés general,
                                que iremos agregando para tu beneficio.
                            </p>
                        </td>
                    </tr>

                    {{-- Nota correo --}}
                    <tr>
                        <td style="padding:10px 35px 0 35px; color:#1a3c6e; font-size:12.5px; line-height:1.65; text-align:justify;">
                            <p style="margin:0 0 10px 0;">
                                Aprovechamos la oportunidad para recordarte que tu correo electrónico
                                personal es la principal herramienta de comunicación para hacerte llegar
                                notificaciones del SIP, laborales y temas afines, por lo que es importante
                                que lo mantengas siempre actualizado y comunicaros en caso de cambios.
                                Según lo que actualmente se encuentra registrado a partir de tu
                                Declaración Jurada de ingreso a la empresa, tu correo electrónico personal es:
                            </p>
                        </td>
                    </tr>

                    {{-- Correo personal --}}
                    <tr>
                        <td style="padding:2px 35px 12px 35px; text-align:center;">
                            <p style="margin:0; font-size:13px; font-weight:bold; color:#1a3c6e;">
                                {{ $datos['correo'] }}
                            </p>
                        </td>
                    </tr>

                    {{-- Despedida --}}
                    <tr>
                        <td style="padding:0 35px; color:#1a3c6e; font-size:12.5px; line-height:1.65;">
                            <p style="margin:0 0 15px 0;">
                                A nombre de <strong>GRUPO SOLAR</strong> te deseamos éxitos.
                            </p>
                        </td>
                    </tr>

                    {{-- Firma: Atentamente (izq) + Firma (der) --}}
                    <tr>
                        <td style="padding:5px 35px 10px 35px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="50%" valign="bottom" style="color:#1a3c6e; font-size:12.5px; padding-bottom:20px;">
                                        Atentamente,
                                    </td>
                                    <td width="50%" align="right" valign="bottom">
                                        <img src="{{ $message->embed(public_path('images/email/firma-mparedes.png')) }}"
                                             alt="Firma" width="200" style="display:block; margin-left:auto;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer con correos (gris, conectado con la firma) --}}
                    <tr>
                        <td style="background-color:#f0f0f0; padding:12px 35px;">
                            <p style="margin:0 0 8px 0; color:#1a3c6e; font-size:11.5px; text-align:center;">
                                Para cualquier consulta tenemos a tu servicio los siguientes correos:
                            </p>
                            <p style="margin:0 0 3px 0; text-align:center;">
                                <a href="mailto:sip@gruposolmar.com.pe" style="color:#1a3c6e; font-size:11.5px; text-decoration:underline; font-weight:bold;">sip@gruposolmar.com.pe</a>
                            </p>
                            <p style="margin:0; text-align:center;">
                                <a href="mailto:serviciosocial@gruposolmar.com.pe" style="color:#1a3c6e; font-size:11.5px; text-decoration:underline; font-weight:bold;">serviciosocial@gruposolmar.com.pe</a>
                            </p>
                        </td>
                    </tr>


                </table>
            </td>
        </tr>
    </table>

</body>
</html>
