export async function printOrderReceipt(country, reference) {
    const response = await window.axios.get('/dashboard-api/orders/reference', {
        params: {
            country,
            reference,
        },
    });
    const payload = response.data.data || {};
    const order = payload.order || {};
    const products = payload.products || [];
    const currency = currencyForCountry(order.countryId || country);
    const printWindow = window.open('', '_blank', 'width=900,height=700');

    if (!printWindow) {
        return;
    }

    printWindow.document.open();
    printWindow.document.write(receiptHtml(order, products, currency));
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

function receiptHtml(order, products, currency) {
    const deliveryLabel = order.checkout === 'DOMICILIO'
        ? order.shipping?.address || 'Domicilio'
        : `Tienda: ${order.storePickup?.storeName || 'N/D'}`;
    const cardRow = String(order.payment?.type || '').toUpperCase() === 'TARJETA'
        ? `<tr><th>Tarjeta</th><td>${escapeHtml(order.payment?.card || 'N/D')}</td></tr>`
        : '';
    const refundRows = order.refund?.hasRefund
        ? `
            <tr><th>Devolucion</th><td>-${currency} ${formatMoney(order.totals?.refund)}</td></tr>
            <tr><th>Total final</th><td>${currency} ${formatMoney(order.totals?.billedNet)}</td></tr>
        `
        : '';

    return `<!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Comprobante ${escapeHtml(order.reference || '')}</title>
            <style>
                body { color: #111827; font-family: Arial, sans-serif; margin: 32px; }
                .header { border-bottom: 2px solid #1d4ed8; margin-bottom: 24px; padding-bottom: 16px; }
                .brand { color: #1d4ed8; font-size: 12px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
                h1 { font-size: 24px; margin: 6px 0 0; }
                h2 { font-size: 17px; margin: 24px 0 10px; }
                table { border-collapse: collapse; margin-top: 12px; width: 100%; }
                th, td { border: 1px solid #d1d5db; padding: 9px; text-align: left; vertical-align: top; }
                th { background: #f3f4f6; }
                .meta th { width: 30%; }
                .right { text-align: right; }
                .small { color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="header">
                <p class="brand">St. Jack's</p>
                <h1>Comprobante de pedido</h1>
                <p class="small">Referencia ${escapeHtml(order.reference || 'N/D')}</p>
            </div>

            <table class="meta">
                <tr><th>Referencia</th><td>${escapeHtml(order.reference || 'N/D')}</td></tr>
                <tr><th>Fecha pedido</th><td>${escapeHtml(formatDateTime(order.createdAt))}</td></tr>
                <tr><th>Fecha pago</th><td>${escapeHtml(formatDateTime(order.paidAt))}</td></tr>
                <tr><th>Estado</th><td>${escapeHtml(order.status || 'N/D')}</td></tr>
                <tr><th>Cliente</th><td>${escapeHtml(order.customer?.name || 'N/D')}</td></tr>
                <tr><th>Email</th><td>${escapeHtml(order.customer?.email || 'N/D')}</td></tr>
                <tr><th>Entrega</th><td>${escapeHtml(deliveryLabel)}</td></tr>
                <tr><th>Pago</th><td>${escapeHtml(order.payment?.type || 'N/D')}</td></tr>
                ${cardRow}
                <tr><th>Autorizacion</th><td>${escapeHtml(order.payment?.authorization || 'N/D')}</td></tr>
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
                    ${products.map((product) => `
                        <tr>
                            <td>${escapeHtml(product.sku || '')}</td>
                            <td>${escapeHtml(product.name || '')}</td>
                            <td>${escapeHtml(product.size || '')}</td>
                            <td class="right">${formatNumber(product.quantity)}</td>
                            <td class="right">${product.billedQuantity ?? '-'}</td>
                            <td class="right">${currency} ${formatMoney(product.price)}</td>
                            <td class="right">${currency} ${formatMoney(product.billedSubtotal ?? product.chargedSubtotal)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>

            <h2>Totales</h2>
            <table class="meta">
                <tr><th>Productos</th><td>${currency} ${formatMoney(order.totals?.products)}</td></tr>
                <tr><th>Envio</th><td>${currency} ${formatMoney(order.totals?.shipping)}</td></tr>
                <tr><th>Total aprobado</th><td>${currency} ${formatMoney(order.totals?.paid)}</td></tr>
                <tr><th>Facturado</th><td>${currency} ${formatMoney(order.totals?.billed)}</td></tr>
                ${refundRows}
            </table>

            <p class="small">Gracias por comprar en St. Jack's.</p>
        </body>
        </html>`;
}

function currencyForCountry(country) {
    const value = String(country || '');

    if (value === '2') {
        return 'Q';
    }

    if (value === '3') {
        return 'CRC';
    }

    return 'USD';
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
}

function formatDateTime(value) {
    if (!value || value === '0000-00-00 00:00:00') {
        return 'N/D';
    }

    return String(value).replace('T', ' ').slice(0, 19);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
