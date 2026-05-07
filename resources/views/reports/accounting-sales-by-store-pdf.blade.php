<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { color: #111827; font-family: "DejaVu Sans", sans-serif; font-size: 10px; line-height: 1.35; }
        h2, h4 { margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #d1d5db; padding: 5px; vertical-align: top; }
        th { background: #e7e7e7; font-weight: bold; }
        .header td, .meta td { border: 0; }
        .meta { margin: 14px 0 18px; width: auto; }
        .meta td { padding: 2px 8px 2px 0; }
        .right { text-align: right; }
        .center { text-align: center; }
        .nested td { border: 0; padding: 3px; }
        .alt { background: #f7f7f7; }
        .summary th { background: #e7e7e7; border-top: 1px solid #111827; }
    </style>
</head>
<body>
@php
    $filters = $report['filters'] ?? [];
    $summary = $report['summary'] ?? [];
    $currency = $report['currency']['symbol'] ?? '';
    $rows = $report['rows'] ?? [];
    $money = static fn ($value) => number_format((float) ($value ?? 0), 2, '.', ',');
    $date = static fn ($value) => $value ? date('d/m/Y', strtotime((string) $value)) : 'N/D';
    $range = ($filters['startDate'] ?? '') === ($filters['endDate'] ?? '')
        ? $date($filters['startDate'] ?? null)
        : $date($filters['startDate'] ?? null).' al '.$date($filters['endDate'] ?? null);
@endphp

<table class="header">
    <tr>
        <td style="width: 70%;">
            <h2>{{ $filters['company'] ?? 'STJ' }}</h2>
            <h4>Reporte de venta en linea por tienda</h4>
        </td>
        <td class="right" style="width: 30%; font-size: 18px; font-weight: bold; color: #2563eb;">ST. JACK'S</td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td><strong>Fecha:</strong></td>
        <td>{{ $range }}</td>
    </tr>
    <tr>
        <td><strong>Tienda:</strong></td>
        <td>{{ ($filters['store'] ?? '0') === '0' ? 'Todas' : ($filters['storeName'] ?? 'N/D') }}</td>
    </tr>
</table>

Resumen de transacciones realizadas:
<br><br>

<table>
    <thead>
        <tr>
            <th style="width: 10%;">Fecha</th>
            <th style="width: 10%;">Tienda</th>
            <th style="width: 14%;">Ref</th>
            <th style="width: 24%;">Cliente</th>
            <th style="width: 9%;">Ticket</th>
            <th style="width: 13%;">Autorizacion</th>
            <th class="center" style="width: 8%;">Cantidad</th>
            <th class="right" style="width: 12%;">Total venta</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $index => $row)
            <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                <td>{{ $date($row['paidAt'] ?? null) }}</td>
                <td>{{ $row['store'] ?? '' }}</td>
                <td>{{ $row['reference'] ?? '' }}</td>
                <td>{{ $row['customer'] ?? '' }}</td>
                <td>{{ $row['ticket'] ?? '' }}</td>
                <td>{{ $row['authorization'] ?? '' }}</td>
                <td class="center">{{ $row['items'] ?? 0 }}</td>
                <td class="right">{{ $money($row['amount'] ?? 0) }}</td>
            </tr>
            @if ($row['hasRefund'] ?? false)
                <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td colspan="3"></td>
                    <td colspan="5">
                        <table class="nested">
                            <tr>
                                <td style="width: 30%;"><strong>Estado pedido</strong></td>
                                <td style="width: 30%;"><strong>Estado productos</strong></td>
                                <td class="center" style="width: 25%;"><strong>Devolucion realizada</strong></td>
                                <td class="right" style="width: 15%;"><strong>Devolucion</strong></td>
                            </tr>
                            <tr>
                                <td>{{ $row['status'] ?? '' }}</td>
                                <td>{{ $row['productStatus'] ?? '' }}</td>
                                <td class="center">{{ $row['refundStatus'] ?? '' }}</td>
                                <td class="right">{{ $money($row['refund'] ?? 0) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            @endif
        @empty
            <tr>
                <td class="center" colspan="8">No hay transacciones para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot class="summary">
        <tr>
            <th class="right" colspan="6">Total</th>
            <th class="center">{{ number_format((int) ($summary['items'] ?? 0)) }}</th>
            <th class="right">{{ $currency }}{{ $money($summary['amount'] ?? 0) }}</th>
        </tr>
        <tr>
            <th class="right" colspan="7">Devolucion</th>
            <th class="right">{{ $currency }}{{ $money($summary['refund'] ?? 0) }}</th>
        </tr>
    </tfoot>
</table>
</body>
</html>
