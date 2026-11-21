
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4 landscape;
            margin: 15px;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td {
            border: 1px solid #777;
            padding: 5px;
            vertical-align: middle;
        }

        .encabezado {
            height: 48px;
            text-align: center;
            font-weight: bold;
        }

        .logo-sip {
            width: 150px;
            height: 45px;
        }

        .logo-sol {
            width: 110px;
            height: 45px;
        }

        .titulo {
            font-size: 14px;
            text-align: center;
            font-weight: bold;
        }

        .texto {
            height: 45px;
            text-align: left;
            font-size: 11px;
        }

        .cabecera {
            background-color: #eeeeee;
            text-align: center;
            font-weight: bold;
            height: 27px;
        }

        .dato {
            height: 27px;
            font-size: 10px;
        }

        .retorno {
            color: red;
        }

        .mensaje {
            font-size: 10px;
            font-weight: bold;
            height: 30px;
        }

        .firmas {
            margin-top: 12px;
            border: none;
        }

        .firmas td {
            border: none;
            text-align: center;
        }

        .firma-imagen {
            width: 220px;
            height: 115px;
        }

        .trabajador {
            width: 250px;
            margin: auto;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 4px;
        }

        .texto-trabajador {
            font-size: 9px;
            font-weight: bold;
        }
    </style>
</head>

<body>

@php
    $fechaIngreso = !empty($solicitud->FECH_INGRE_PLAN)
        ? date('d/m/Y', strtotime($solicitud->FECH_INGRE_PLAN))
        : '';

    $fechaInicio = !empty($solicitud->FECHA_INI_DESC)
        ? date('d/m/Y', strtotime($solicitud->FECHA_INI_DESC))
        : '';

    $fechaRetorno = !empty($solicitud->FECHA_RETORNO_DESC)
        ? date('d/m/Y', strtotime($solicitud->FECHA_RETORNO_DESC))
        : '';

    $fechaSolicitud = !empty($solicitud->FECHA_SOLICITUD)
        ? date('d/m/Y', strtotime($solicitud->FECHA_SOLICITUD))
        : '';
@endphp

<table>
    <tr class="encabezado">
        <td width="25%">
            <img src="{{ public_path('images/LOGO_SIP_FONDO_1.jpg') }}"
                 class="logo-sip">
        </td>
        <td class="titulo" width="50%">
            {{ $solicitud->TITULO }}
        </td>
        <td width="25%">
            <img src="{{ public_path('images/SOL_SECURITY.png') }}"
                 class="logo-sol">
        </td>
    </tr>

    <tr>
        <td colspan="3" class="texto">
            {{ $solicitud->TEXTO_1 }}
        </td>
    </tr>

    <tr>
        <td colspan="1" class="cabecera">Nombres y Apellidos</td>
        <td colspan="1" class="cabecera">Documento de identidad</td>
        <td colspan="1" class="cabecera">Sucursal</td>
    </tr>

    <tr>
        <td class="dato">{{ $solicitud->APEL_NOMB }}</td>
        <td class="dato">
            {{ $solicitud->TIPO_DOCU }} {{ $solicitud->NRO_DOCU_IDEN }}
        </td>
        <td class="dato">{{ $solicitud->SUCURSAL }}</td>
    </tr>

    <tr>
        <td class="cabecera">Cargo</td>
        <td class="cabecera">Fecha de ingreso</td>
        <td class="cabecera">Periodo laborado</td>
    </tr>

    <tr>
        <td class="dato">{{ $solicitud->CARGO }}</td>
        <td class="dato">{{ $fechaIngreso }}</td>
        <td class="dato">{{ $solicitud->PERIODO_LABORADO }}</td>
    </tr>

    <tr>
        <td class="cabecera">Fecha de inicio del descanso</td>
        <td class="cabecera retorno">Fecha de retorno al trabajo</td>
        <td class="cabecera">Total de días de descanso vacacional</td>
    </tr>

    <tr>
        <td class="dato">{{ $fechaInicio }}</td>
        <td class="dato">{{ $fechaRetorno }}</td>
        <td class="dato">{{ $solicitud->TOTAL_DIAS_DESC }}</td>
    </tr>

    <tr>
        <td class="cabecera">Días pendientes de descanso vacacional</td>
        <td colspan="2" class="dato">
            {{ $solicitud->TOTAL_DIAS_PEND }}
        </td>
    </tr>

    <tr>
        <td colspan="3" class="mensaje">
            Estimado {{ $solicitud->NOMBRE }},
            tu solicitud ha sido registrada y aprobada el {{ $fechaSolicitud }}.
        </td>
    </tr>
</table>

<table class="firmas">
    <tr>
        <td width="50%">
            <img src="{{ public_path('images/RRHHMILA.png') }}"
                 class="firma-imagen">
        </td>
        <td width="50%">
            <div class="trabajador">
                {{ $solicitud->NOMB_APEL }}<br>
                {{ $solicitud->NRO_DOCU_IDEN }}
                <hr>
                <span class="texto-trabajador">TRABAJADOR</span>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
