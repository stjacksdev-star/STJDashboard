<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 18px 22px;
        }

        body {
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            line-height: 1.35;
        }

        h1 {
            font-size: 24px;
            margin: 0 0 10px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #d1d5db;
            padding: 5px 4px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            font-size: 9px;
            text-align: left;
        }

        .header td {
            border: 0;
            padding: 0 0 10px;
        }

        .brand {
            color: #2563eb;
            font-size: 18px;
            font-weight: bold;
            text-align: right;
        }

        .meta {
            margin-bottom: 12px;
        }

        .meta td {
            border: 0;
            padding: 2px 10px 2px 0;
        }

        .summary {
            margin-bottom: 12px;
        }

        .summary th,
        .summary td {
            border: 1px solid #d1d5db;
            font-size: 11px;
            padding: 7px;
        }

        .summary td {
            font-weight: bold;
            text-align: right;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .totals th,
        .totals td {
            background: #f3f4f6;
            border-top: 1px solid #9ca3af;
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
        }
    </style>
</head>
<body>
@php
    $filters = $report['filters'] ?? [];
    $summary = $report['summary'] ?? [];
    $currency = $report['currency']['symbol'] ?? '';
    $rows = $report['rows'] ?? [];
    $money = static fn ($value) => number_format((float) ($value ?? 0), 2);
@endphp

<table class="header">
    <tr>
        <td>
            <h1>Corte Virtual</h1>
            <div class="muted">Generado: {{ $generatedAt }}</div>
        </td>
        <td class="brand">ST. JACK'S</td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td><strong>Tienda:</strong> {{ $filters['storeName'] ?? 'N/D' }}</td>
        <td><strong>Codigo:</strong> {{ $filters['store'] ?? 'N/D' }}</td>
        <td><strong>Fecha:</strong> {{ $filters['date'] ?? 'N/D' }}</td>
    </tr>
</table>

<table class="summary">
    <tr>
        <th>Transacciones</th>
        <th>Tarjeta</th>
        <th>Efectivo</th>
        <th>Total</th>
    </tr>
    <tr>
        <td>{{ number_format((int) ($summary['transactions'] ?? 0)) }}</td>
        <td>{{ $currency }}{{ $money($summary['card'] ?? 0) }}</td>
        <td>{{ $currency }}{{ $money($summary['cash'] ?? 0) }}</td>
        <td>{{ $currency }}{{ $money($summary['total'] ?? 0) }}</td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th style="width: 11%;">Fecha compra</th>
            <th style="width: 10%;">Estado</th>
            <th style="width: 8%;">Forma pago</th>
            <th style="width: 10%;">Referencia</th>
            <th style="width: 8%;">Ticket</th>
            <th style="width: 11%;">Fecha facturado</th>
            <th style="width: 8%;">Autorizacion</th>
            <th class="right" style="width: 8%;">Monto cobrado</th>
            <th class="right" style="width: 6%;">Envio</th>
            <th class="right" style="width: 7%;">Devolucion</th>
            <th class="right" style="width: 7%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['purchaseDate'] ?? '' }}</td>
                <td>{{ $row['status'] ?? '' }}</td>
                <td>{{ $row['paymentType'] ?? '' }}</td>
                <td>{{ $row['reference'] ?? '' }}</td>
                <td>{{ $row['ticket'] ?? '' }}</td>
                <td>{{ $row['processedAt'] ?? '' }}</td>
                <td>{{ $row['authorization'] ?? '' }}</td>
                <td class="right">{{ $money($row['chargedAmount'] ?? 0) }}</td>
                <td class="right">{{ $money($row['shipping'] ?? 0) }}</td>
                <td class="right">{{ $money($row['refund'] ?? 0) }}</td>
                <td class="right">{{ $money($row['total'] ?? 0) }}</td>
            </tr>
        @empty
            <tr>
                <td class="center muted" colspan="11">No hay transacciones para los filtros seleccionados.</td>
            </tr>
        @endforelse

        <tr class="totals">
            <th class="right" colspan="10">TARJETA:</th>
            <td class="right">{{ $money($summary['card'] ?? 0) }}</td>
        </tr>
        <tr class="totals">
            <th class="right" colspan="10">EFECTIVO:</th>
            <td class="right">{{ $money($summary['cash'] ?? 0) }}</td>
        </tr>
        <tr class="totals">
            <th class="right" colspan="10">TOTAL:</th>
            <td class="right">{{ $currency }}{{ $money($summary['total'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>
</body>
</html>
