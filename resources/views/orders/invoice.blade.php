<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->invoice_number }} - Silva LPG Trading</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px; color: #1f2937; font: 14px Arial, sans-serif; }
        .invoice { max-width: 800px; margin: 0 auto; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin: 0 auto 16px; max-width: 800px; }
        button, .back { border: 0; border-radius: 6px; padding: 10px 14px; background: #7f1d1d; color: #fff; font-size: 14px; text-decoration: none; cursor: pointer; }
        .back { background: #f3f4f6; color: #374151; }
        header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #7f1d1d; padding-bottom: 20px; }
        h1 { margin: 0 0 8px; color: #7f1d1d; font-size: 26px; }
        h2 { margin: 0 0 12px; font-size: 16px; }
        p { margin: 5px 0; }
        .muted { color: #6b7280; }
        .meta { text-align: right; }
        .details { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin: 24px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 11px 8px; text-align: left; }
        th { background: #f9fafb; color: #4b5563; font-weight: 600; }
        .number { text-align: right; white-space: nowrap; }
        .totals { width: 300px; margin: 18px 0 0 auto; }
        .totals div { display: flex; justify-content: space-between; padding: 5px 0; }
        .grand { border-top: 1px solid #9ca3af; margin-top: 5px; padding-top: 10px !important; font-size: 17px; font-weight: 700; }
        footer { border-top: 1px solid #e5e7eb; margin-top: 40px; padding-top: 16px; text-align: center; color: #6b7280; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            .invoice { max-width: none; }
        }
        @media (max-width: 600px) {
            body { padding: 16px; }
            header { flex-direction: column; }
            .meta { text-align: left; }
            .details { grid-template-columns: 1fr; gap: 18px; }
            .totals { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a class="back" href="javascript:history.back()">Back to order</a>
        <button type="button" onclick="window.print()">Print Invoice</button>
    </div>
    <main class="invoice">
        <header>
            <div>
                <h1>Silva LPG Trading</h1>
                <p class="muted">Sales Invoice</p>
            </div>
            <div class="meta">
                <h2>Invoice {{ $order->invoice_number }}</h2>
                <p><strong>Order:</strong> {{ $order->order_code }}</p>
                <p><strong>Invoice date:</strong> {{ $order->order_date->format('M j, Y') }}</p>
                <p><strong>Ordered at:</strong> {{ $order->ordered_at?->format('M j, Y g:i A') }}</p>
            </div>
        </header>

        <section class="details">
            <div>
                <h2>Bill to</h2>
                <p>{{ $order->customer->name }}</p>
                <p>{{ $order->customer->phone }}</p>
                <p>{{ $order->fulfillment_type === 'pickup' ? 'Pick-up at store' : $order->delivery_address }}</p>
            </div>
            <div>
                <h2>Order details</h2>
                <p><strong>Fulfillment:</strong> {{ $order->fulfillment_type === 'pickup' ? 'Pick-up' : 'Delivery' }}</p>
                @if($order->fulfillment_type === 'delivery')
                    <p><strong>Scheduled delivery:</strong> {{ $order->scheduled_delivery_date?->format('M j, Y') ?? 'Not scheduled' }}</p>
                @endif
                <p><strong>Payment:</strong> {{ strtoupper($order->payment_method) }} — {{ $order->payment_status_label }}</p>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="number">Qty</th>
                    <th class="number">Gas price</th>
                    <th class="number">Cylinder price</th>
                    <th class="number">Line total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            {{ $item->product->name ?? 'Deleted product' }}
                            @if($item->cylinder) <span class="muted">({{ $item->cylinder->name }})</span> @endif
                            <div class="muted">{{ $item->has_own_cylinder ? 'Exchange' : 'Cylinder purchased' }}</div>
                        </td>
                        <td class="number">{{ $item->quantity }}</td>
                        <td class="number">₱{{ number_format($item->unit_gas_price, 2) }}</td>
                        <td class="number">₱{{ number_format($item->unit_cylinder_fee, 2) }}</td>
                        <td class="number">₱{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="totals">
            <div><span>Subtotal</span><span>₱{{ number_format($order->subtotal, 2) }}</span></div>
            <div><span>Cylinder purchases</span><span>₱{{ number_format($order->cylinder_fee_total, 2) }}</span></div>
            <div class="grand"><span>Total</span><span>₱{{ number_format($order->grand_total, 2) }}</span></div>
        </section>

        <footer>Thank you for your order.</footer>
    </main>
</body>
</html>
