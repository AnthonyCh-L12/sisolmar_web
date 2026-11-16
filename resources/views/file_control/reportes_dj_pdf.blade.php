<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px 22px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 7px; }
        h1 { margin: 0 0 7px; color: #123b59; font-size: 15px; }
        .meta { margin-bottom: 12px; color: #53657c; font-size: 8px; }
        .pdf-header { width: 100%; margin: 0 0 7px; border-collapse: collapse; }
        .pdf-header td { padding: 0; border: 0; background: transparent; vertical-align: middle; }
        .pdf-logo-cell, .pdf-spacer-cell { width: 15%; }
        .pdf-title-cell { width: 70%; text-align: center; }
        .pdf-logo { display: block; width: 130px; height: 62px; }
        .pdf-header h1 { margin: 0; color: #123b59; font-size: 15px; text-align: center; }
        .stats { width: 100%; margin: 0 0 12px; border-collapse: collapse; table-layout: fixed; }
        .stats th, .stats td { width: auto; padding: 5px 4px; border: 1px solid #d1d5db; background: #e5e7eb; color: #374151; text-align: center; }
        .stats th { font-size: 7px; }
        .stats td { color: #111827; font-size: 9px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { padding: 5px 4px; background: #334155; color: #fff; text-align: center; font-size: 6px; }
        td { padding: 4px; border: 1px solid #d8e1eb; text-align: center; vertical-align: top; word-wrap: break-word; }
        tr:nth-child(even) td { background: #f8fafc; }
        .footer { position: fixed; bottom: -20px; left: 0; right: 0; text-align: right; color: #64748b; font-size: 7px; }
        .footer:after { content: "Página " counter(page) " de " counter(pages); }
        .empty { padding: 18px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    <table class="pdf-header">
        <tr>
            <td class="pdf-logo-cell">
                @if(!empty($logoDataUri))
                    <img class="pdf-logo" src="{{ $logoDataUri }}" alt="SOL Security">
                @endif
            </td>
            <td class="pdf-title-cell"><h1>{{ $titulo }}</h1></td>
            <td class="pdf-spacer-cell"></td>
        </tr>
    </table>
    @if(isset($stats['foto_ok']))
        @php($totalEvaluado = max((int) $stats['personal'], 1))
        <table class="stats">
            <thead><tr><th>Generado</th><th>Personal evaluado</th><th>Foto OK</th><th>DNI completo</th><th>Incompletos</th></tr></thead>
            <tbody><tr>
                <td>{{ $stats['generado'] }}</td>
                <td>{{ number_format((int) $stats['personal'], 0, ',', '.') }}</td>
                <td>{{ number_format((int) $stats['foto_ok'], 0, ',', '.') }} ({{ number_format($stats['foto_ok'] / $totalEvaluado * 100, 1, ',', '.') }}%)</td>
                <td>{{ number_format((int) $stats['dni_ok'], 0, ',', '.') }} ({{ number_format($stats['dni_ok'] / $totalEvaluado * 100, 1, ',', '.') }}%)</td>
                <td>{{ number_format((int) $stats['faltan'], 0, ',', '.') }}</td>
            </tr></tbody>
        </table>
    @elseif(isset($stats['sin_dj']))
        <table class="stats">
            <thead><tr><th>Generado</th><th>Personal evaluado</th><th>Sin DJ escaneada</th></tr></thead>
            <tbody><tr>
                <td>{{ $stats['generado'] }}</td>
                <td>{{ number_format((int) $stats['personal'], 0, ',', '.') }}</td>
                <td>{{ number_format((int) $stats['sin_dj'], 0, ',', '.') }}</td>
            </tr></tbody>
        </table>
    @elseif(isset($stats['mayoria_edad']))
        <table class="stats">
            <thead><tr><th>Generado</th><th>Personal</th><th>Hijos por cumplir 18</th></tr></thead>
            <tbody><tr>
                <td>{{ $stats['generado'] }}</td>
                <td>{{ number_format((int) $stats['personal'], 0, ',', '.') }}</td>
                <td>{{ number_format((int) $stats['mayoria_edad'], 0, ',', '.') }}</td>
            </tr></tbody>
        </table>
    @elseif(isset($stats['hijos_mayores']))
        <table class="stats">
            <thead><tr><th>Generado</th><th>Personal</th><th>Hijos mayores de edad</th></tr></thead>
            <tbody><tr>
                <td>{{ $stats['generado'] }}</td>
                <td>{{ number_format((int) $stats['personal'], 0, ',', '.') }}</td>
                <td>{{ number_format((int) $stats['hijos_mayores'], 0, ',', '.') }}</td>
            </tr></tbody>
        </table>
    @else
        @php($totalEvaluado = max((int) ($stats['evaluados'] ?? 0), 1))
        <table class="stats">
            <thead><tr><th>Generado</th><th>Personal evaluado</th><th>Sin faltantes</th><th>Con faltantes</th><th>Hijos menores de 18</th></tr></thead>
            <tbody><tr>
                <td>{{ $stats['generado'] }}</td>
                <td>{{ number_format((int) $stats['evaluados'], 0, ',', '.') }}</td>
                <td>{{ number_format((int) $stats['ok'], 0, ',', '.') }} ({{ number_format($stats['ok'] / $totalEvaluado * 100, 1, ',', '.') }}%)</td>
                <td>{{ number_format((int) $stats['faltan'], 0, ',', '.') }}</td>
                <td>{{ number_format((int) $stats['hijos'], 0, ',', '.') }}</td>
            </tr></tbody>
        </table>
    @endif

    @if($filas === [])
        <div class="empty">No se encontraron registros con los filtros seleccionados.</div>
    @else
        <table>
            @if(isset($stats['mayoria_edad']))
                <colgroup>
                    <col style="width:4%"><col style="width:10%"><col style="width:25%"><col style="width:9%">
                    <col style="width:14%"><col style="width:10%"><col style="width:10%"><col style="width:7%"><col style="width:11%">
                </colgroup>
            @endif
            <thead><tr>@foreach($columnas as $etiqueta => $clave)<th>{{ $etiqueta }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach($filas as $fila)
                    <tr>@foreach($columnas as $clave)<td>{{ $fila[$clave] ?? '—' }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <div class="footer"></div>
</body>
</html>
