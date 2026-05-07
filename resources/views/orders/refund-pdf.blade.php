<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 32px;
        }

        body {
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        h1 {
            font-size: 24px;
            margin: 0;
        }

        h2 {
            font-size: 15px;
            margin: 22px 0 8px;
        }

        table {
            border-collapse: collapse;
            margin-top: 16px;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 9px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            width: 32%;
        }

        pre {
            background: #f9fafb;
            border: 1px solid #d1d5db;
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 9px;
            padding: 10px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .header {
            border-bottom: 2px solid #2563eb;
            margin-bottom: 22px;
            padding-bottom: 14px;
        }

        .brand {
            color: #2563eb;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .muted {
            color: #6b7280;
            font-size: 11px;
        }
    </style>
</head>
<body>
@php
    $money = static fn ($value) => number_format((float) ($value ?? 0), 2);
@endphp

<div class="header">
    <div class="brand">St. Jack's Admin</div>
    <h1>Comprobante de devolucion</h1>
    <div class="muted">Generado: {{ $generatedAt }}</div>
</div>

<table>
    <tr>
        <th>Referencia</th>
        <td>{{ $refund['ref'] ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Cliente</th>
        <td>{{ $refund['customer'] ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Email</th>
        <td>{{ $refund['email'] ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Identificacion</th>
        <td>{{ $refund['identification'] ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Telefono</th>
        <td>{{ $refund['phone'] ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Estado devolucion</th>
        <td>{{ $refund['refundLabel'] ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Fecha devolucion</th>
        <td>{{ $refund['refundAt'] ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Fecha pago</th>
        <td>{{ $refund['paidAt'] ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Forma de pago</th>
        <td>{{ $refund['paymentType'] ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Monto pagado</th>
        <td>{{ $money($refund['paidAmount'] ?? 0) }}</td>
    </tr>
    <tr>
        <th>Monto devolucion</th>
        <td>{{ $money($refund['refundAmount'] ?? 0) }}</td>
    </tr>
    <tr>
        <th>Tienda</th>
        <td>{{ $refund['storeName'] ?? $refund['storeCode'] ?? 'N/D' }}</td>
    </tr>
    <tr>
        <th>Aprobado</th>
        <td>SI</td>
    </tr>
</table>

<h2>Respuesta servicio</h2>
<pre>{{ $refund['serviceRaw'] ?? '' }}</pre>
</body>
</html>
