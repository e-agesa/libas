<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 11px; color: #000; width: 100%; }
        .receipt { padding: 10px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .brand { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
        .brand-sub { font-size: 9px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .item-name { max-width: 120px; word-wrap: break-word; }
        .total-row td { padding-top: 4px; }
        .big-total { font-size: 14px; font-weight: bold; }
        .footer { font-size: 9px; color: #444; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Header -->
        <div class="center">
            <div class="brand">{{ $company->business_name ?? 'Libas TMS' }}</div>
            @if($company->tagline)
                <div class="brand-sub">{{ $company->tagline }}</div>
            @endif
            @if($company->phone)
                <div>{{ $company->phone }}</div>
            @endif
            @if($company->address)
                <div>{{ $company->address }}</div>
            @endif
        </div>

        <div class="divider"></div>

        <!-- Receipt info -->
        <table>
            <tr>
                <td class="bold">Receipt #:</td>
                <td class="right">{{ $invoice->invoice_number }}</td>
            </tr>
            <tr>
                <td>Date:</td>
                <td class="right">{{ $invoice->date->format('d/m/Y H:i') }}</td>
            </tr>
            @if($invoice->client)
            <tr>
                <td>Customer:</td>
                <td class="right">{{ $invoice->client->name }}</td>
            </tr>
            @endif
            <tr>
                <td>Payment:</td>
                <td class="right">{{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- Items -->
        <table>
            <tr class="bold">
                <td>Item</td>
                <td class="center" style="width:30px">Qty</td>
                <td class="right" style="width:70px">Total</td>
            </tr>
        </table>
        <div class="divider"></div>
        <table>
            @foreach($invoice->lineItems as $item)
            <tr>
                <td class="item-name">{{ $item->collection->name ?? $item->description ?? 'Item' }}</td>
                <td class="center" style="width:30px">{{ $item->quantity }}</td>
                <td class="right" style="width:70px">{{ number_format($item->line_total, 0) }}</td>
            </tr>
            @if($item->quantity > 1)
            <tr>
                <td colspan="3" style="font-size:9px; color:#666;">  @ {{ number_format($item->unit_price, 0) }} each</td>
            </tr>
            @endif
            @endforeach
        </table>

        <div class="divider"></div>

        <!-- Totals -->
        <table>
            <tr>
                <td>Subtotal:</td>
                <td class="right">KES {{ number_format($invoice->subtotal, 0) }}</td>
            </tr>
            @if($invoice->discount > 0)
            <tr>
                <td>Discount:</td>
                <td class="right">-KES {{ number_format($invoice->discount, 0) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="big-total">TOTAL:</td>
                <td class="right big-total">KES {{ number_format($invoice->total, 0) }}</td>
            </tr>
            <tr>
                <td>Paid:</td>
                <td class="right">KES {{ number_format($invoice->amount_paid, 0) }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- Notes -->
        @if($invoice->notes)
        <div style="font-size:9px; margin-bottom:4px;">{{ $invoice->notes }}</div>
        <div class="divider"></div>
        @endif

        <!-- Footer -->
        <div class="center footer">
            @if($company->footer_text)
                {{ $company->footer_text }}
            @else
                Thank you for your purchase!
            @endif
            <br>
            {{ $company->business_name ?? 'Libas TMS' }} &middot; {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
