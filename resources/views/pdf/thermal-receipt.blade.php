<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $invoice->invoice_number }}</title>
    <style>
        /*
         * 80mm thermal roll, sized for a 72mm printable width.
         *
         * The type is deliberately larger than a typical receipt: this one is
         * handed across the counter and read on the spot, and the first print
         * came back marked "make it big". The shop's logo leads, the contact
         * details close, and there is clear space at the foot for the thank-you.
         */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 13px; color: #000; width: 100%; }
        .receipt { padding: 8px 10px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 7px 0; }

        /* Nearly the full printable width — the counter asked for it big. */
        .logo-img { max-width: 210px; max-height: 110px; margin-bottom: 7px; }
        .brand { font-size: 22px; font-weight: bold; letter-spacing: 1px; margin-bottom: 3px; }
        .brand-sub { font-size: 11px; margin-bottom: 4px; }

        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        .item-name { word-wrap: break-word; }
        .sub { font-size: 11px; color: #222; }
        .big-total { font-size: 19px; font-weight: bold; }

        /* The thank-you: its own block, with room around it. */
        .thanks { margin-top: 12px; font-size: 15px; font-weight: bold; }
        .thanks-note { margin-top: 6px; font-size: 12px; }
        .thanks-space { height: 34px; }

        .contact { font-size: 12px; line-height: 1.5; }
        .contact-label { font-size: 11px; letter-spacing: 1px; margin-bottom: 3px; }
        .feed { height: 26px; }
    </style>
</head>
<body>
<div class="receipt">

    <div class="center">
        @if($company->logo_base64)
            <img src="{{ $company->logo_base64 }}" class="logo-img" alt="{{ $company->business_name ?? 'Logo' }}">
        @endif
        <div class="brand">{{ $company->business_name ?? 'Libas ul Anwar' }}</div>
        @if($company->tagline)<div class="brand-sub">{{ $company->tagline }}</div>@endif
    </div>

    <div class="divider"></div>

    <table>
        <tr><td>{{ $invoice->type === 'quotation' ? 'Quotation' : 'Invoice' }}</td>
            <td class="right bold">{{ $invoice->invoice_number }}</td></tr>
        <tr><td>Date</td><td class="right">{{ \Carbon\Carbon::parse($invoice->date)->format('d/m/Y') }}</td></tr>
        @if($invoice->client)
        <tr><td>Customer</td><td class="right">{{ $invoice->client->name }}</td></tr>
        @endif
    </table>

    <div class="divider"></div>

    <table>
        <tr class="bold">
            <td>Item</td>
            <td class="center" style="width:38px">Qty</td>
            <td class="right" style="width:74px">Total</td>
        </tr>
        @foreach($invoice->lineItems as $item)
            @php
                // A ridhaa carries its own quantity; when the whole line is a
                // ridhaa the garment quantity stays 1 and would read wrongly.
                $tailoring = (float) $item->craftsmanship_fee + (float) $item->fabric_cost;
                $ridhaaOnly = $tailoring == 0 && $item->ridhaa_qty > 0 && $item->ridhaa_price > 0;
                $qty = $ridhaaOnly ? $item->ridhaa_qty : $item->quantity;
                $qtyLabel = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');

                // A variation is a different thing from its product, so the
                // receipt has to say which one was sold.
                $name = $item->item_type === 'collection'
                    ? ($item->description ?: ($item->collection->name ?? 'Item'))
                    : ($item->description ?: ($item->measurement->garment_type ?? $item->ridhaa_name ?: 'Custom Garment'));
            @endphp
            <tr>
                <td class="item-name">
                    {{ $name }}
                    @if($item->item_type !== 'collection')
                        @if($item->contact)<div class="sub">for {{ $item->contact->name }}</div>@endif
                        @if($item->fabric)<div class="sub">{{ $item->fabric->code ? $item->fabric->code . ' · ' : '' }}{{ $item->fabric->name }}</div>@endif
                        @if(!$ridhaaOnly && $item->ridhaa_qty > 0 && $item->ridhaa_price > 0)
                            <div class="sub">+ {{ $item->ridhaa_name ?: 'Ridhaa' }} x{{ rtrim(rtrim(number_format($item->ridhaa_qty, 2, '.', ''), '0'), '.') }}</div>
                        @endif
                    @endif
                </td>
                <td class="center">{{ $qtyLabel }}</td>
                <td class="right">{{ number_format($item->line_total, 0) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="divider"></div>

    <table>
        @if($invoice->discount > 0 || $invoice->tax > 0)
            <tr><td>Subtotal</td><td class="right">{{ number_format($invoice->subtotal, 0) }}</td></tr>
        @endif
        @if($invoice->discount > 0)
            <tr><td>Discount</td><td class="right">-{{ number_format($invoice->discount, 0) }}</td></tr>
        @endif
        @if($invoice->tax > 0)
            <tr><td>Tax</td><td class="right">{{ number_format($invoice->tax, 0) }}</td></tr>
        @endif
        <tr><td class="big-total">TOTAL</td>
            <td class="right big-total">{{ number_format($invoice->total, 0) }}</td></tr>
        @if($invoice->amount_paid > 0)
            <tr><td>Paid</td><td class="right">{{ number_format($invoice->amount_paid, 0) }}</td></tr>
        @endif
        @if($invoice->balance > 0)
            <tr><td class="bold">Balance</td><td class="right bold">{{ number_format($invoice->balance, 0) }}</td></tr>
        @endif
    </table>

    @if($invoice->payment_method)
        <div class="sub" style="margin-top:5px">Paid by: {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</div>
    @endif

    <div class="divider"></div>

    <div class="center thanks">
        Thank you for shopping at {{ $company->business_name ?? 'Libas ul Anwar' }}!
    </div>

    @if($company->footer_text)
        <div class="center thanks-note">{{ $company->footer_text }}</div>
    @else
        {{-- Blank roll for a handwritten note or a stamp. --}}
        <div class="thanks-space"></div>
    @endif

    <div class="divider"></div>

    <div class="center contact">
        @if($company->address)<div>{{ $company->address }}</div>@endif
        @if($company->phone)<div>{{ $company->phone }}</div>@endif
        @if($company->email)<div>{{ $company->email }}</div>@endif
    </div>

    {{-- Roll left past the print so the tear-off does not cut the last line. --}}
    <div class="feed"></div>

</div>
</body>
</html>
