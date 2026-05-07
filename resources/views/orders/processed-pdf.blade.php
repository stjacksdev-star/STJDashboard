<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 28px 32px;
        }

        body {
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            line-height: 1.4;
        }

        h1 {
            font-size: 24px;
            margin: 0;
        }

        h2 {
            font-size: 15px;
            margin: 20px 0 8px;
        }

        table {
            border-collapse: collapse;
            margin-top: 12px;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
        }

        .header {
            border-bottom: 2px solid #2563eb;
            margin-bottom: 20px;
            padding-bottom: 14px;
        }

        .brand {
            color: #2563eb;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .meta th {
            width: 30%;
        }

        .right {
            text-align: right;
        }

        .muted {
            color: #6b7280;
            font-size: 10px;
        }
    </style>
</head>
<body>
@php
    $money = static fn ($value) => number_format((float) ($value ?? 0), 2);
    $number = static fn ($value) => number_format((float) ($value ?? 0), 0);
    $date = static function ($value) {
        $value = (string) ($value ?? '');
        return $value === '' || $value === '0000-00-00 00:00:00' ? 'N/D' : substr(str_replace('T', ' ', $value), 0, 19);
    };
    $delivery = ($order['checkout'] ?? '') === 'DOMICILIO'
        ? data_get($order, 'shipping.address', 'Domicilio')
        : 'Tienda: '.(data_get($order, 'storePickup.storeName') ?: 'N/D');
@endphp

<div class="header">
    <div class="brand">St. Jack's</div>
    <h1>Comprobante de pedido</h1>
    <div class="muted">Referencia {{ $order['reference'] ?? 'N/D' }} | Generado {{ $generatedAt }}</div>
</div>

<table class="meta">
    <tr><th>Referencia</th><td>{{ $order['reference'] ?? 'N/D' }}</td></tr>
    <tr><th>Fecha pedido</th><td>{{ $date($order['createdAt'] ?? null) }}</td></tr>
    <tr><th>Fecha pago</th><td>{{ $date($order['paidAt'] ?? null) }}</td></tr>
    <tr><th>Fecha procesado</th><td>{{ $date($order['processedAt'] ?? null) }}</td></tr>
    <tr><th>Estado</th><td>{{ $order['status'] ?? 'N/D' }}</td></tr>
    <tr><th>Cliente</th><td>{{ data_get($order, 'customer.name', 'N/D') }}</td></tr>
    <tr><th>Email</th><td>{{ data_get($order, 'customer.email', 'N/D') }}</td></tr>
    <tr><th>Entrega</th><td>{{ $delivery }}</td></tr>
    <tr><th>Pago</th><td>{{ data_get($order, 'payment.type', 'N/D') }}</td></tr>
    @if (strtoupper((string) data_get($order, 'payment.type')) === 'TARJETA')
        <tr><th>Tarjeta</th><td>{{ data_get($order, 'payment.card', 'N/D') }}</td></tr>
    @endif
    <tr><th>Autorizacion</th><td>{{ data_get($order, 'payment.authorization', 'N/D') }}</td></tr>
    <tr><th>Ticket</th><td>{{ data_get($order, 'payment.ticket', 'N/D') }}</td></tr>
</table>

<h2>Productos</h2>
<table>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Descripcion</th>
            <th>Talla</th>
            <th class="right">Cantidad</th>
            <th class="right">Facturado</th>
            <th class="right">Precio</th>
            <th class="right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($products as $product)
            <tr>
                <td>{{ $product['sku'] ?? '' }}</td>
                <td>{{ $product['name'] ?? '' }}</td>
                <td>{{ $product['size'] ?? '' }}</td>
                <td class="right">{{ $number($product['quantity'] ?? 0) }}</td>
                <td class="right">{{ $product['billedQuantity'] ?? '-' }}</td>
                <td class="right">{{ $currency }} {{ $money($product['price'] ?? 0) }}</td>
                <td class="right">{{ $currency }} {{ $money($product['billedSubtotal'] ?? $product['chargedSubtotal'] ?? 0) }}</td>
            </tr>
        @empty
            <tr>
                <td class="muted" colspan="7">No hay productos registrados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<h2>Totales</h2>
<table class="meta">
    <tr><th>Productos</th><td>{{ $currency }} {{ $money(data_get($order, 'totals.products')) }}</td></tr>
    <tr><th>Envio</th><td>{{ $currency }} {{ $money(data_get($order, 'totals.shipping')) }}</td></tr>
    <tr><th>Total aprobado</th><td>{{ $currency }} {{ $money(data_get($order, 'totals.paid')) }}</td></tr>
    <tr><th>Facturado</th><td>{{ $currency }} {{ $money(data_get($order, 'totals.billed')) }}</td></tr>
    @if (data_get($order, 'refund.hasRefund'))
        <tr><th>Devolucion</th><td>-{{ $currency }} {{ $money(data_get($order, 'totals.refund')) }}</td></tr>
        <tr><th>Total final</th><td>{{ $currency }} {{ $money(data_get($order, 'totals.billedNet')) }}</td></tr>
    @endif
</table>

<p class="muted">Gracias por comprar en St. Jack's.</p>
</body>
</html>
