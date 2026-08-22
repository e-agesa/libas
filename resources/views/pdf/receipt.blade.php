<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->type === 'quotation' ? 'Quotation' : 'Receipt' }} - {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #1f2937; line-height: 1.5; }
        .container { max-width: 700px; margin: 0 auto; padding: 30px; }

        /* Header */
        .header { display: table; width: 100%; margin-bottom: 30px; border-bottom: 2px solid #22c55e; padding-bottom: 20px; }
        .header-left { display: table-cell; vertical-align: top; width: 60%; }
        .header-right { display: table-cell; vertical-align: top; width: 40%; text-align: right; }
        .brand { font-size: 24px; font-weight: bold; color: #22c55e; margin-bottom: 4px; }
        .brand-sub { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .brand-contact { font-size: 10px; color: #6b7280; margin-top: 6px; line-height: 1.6; }
        .invoice-title { font-size: 18px; font-weight: bold; color: #1f2937; }
        .invoice-number { font-size: 14px; color: #22c55e; font-weight: bold; margin-top: 2px; }
        .invoice-meta { font-size: 10px; color: #6b7280; margin-top: 8px; }
        .logo-img { max-height: 60px; max-width: 160px; margin-bottom: 8px; }

        /* Client info */
        .client-section { display: table; width: 100%; margin-bottom: 20px; }
        .client-box { display: table-cell; vertical-align: top; width: 50%; }
        .section-label { font-size: 9px; text-transform: uppercase; color: #9ca3af; letter-spacing: 1px; margin-bottom: 4px; font-weight: bold; }
        .client-name { font-size: 14px; font-weight: bold; color: #1f2937; }
        .client-detail { font-size: 11px; color: #6b7280; }

        /* Status badge */
        .status { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .status-draft { background: #f3f4f6; color: #6b7280; }
        .status-issued { background: #dbeafe; color: #1d4ed8; }
        .status-paid { background: #dcfce7; color: #15803d; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        .status-voided { background: #f3f4f6; color: #9ca3af; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f9fafb; padding: 8px 12px; text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        td { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }

        /* Totals */
        .totals { width: 250px; margin-left: auto; }
        .total-row { display: table; width: 100%; padding: 4px 0; }
        .total-label { display: table-cell; text-align: right; padding-right: 15px; color: #6b7280; font-size: 11px; }
        .total-value { display: table-cell; text-align: right; font-size: 11px; font-weight: bold; }
        .total-final { border-top: 2px solid #22c55e; padding-top: 8px; margin-top: 4px; }
        .total-final .total-value { font-size: 16px; color: #22c55e; }

        /* Payments */
        .payments-title { font-size: 12px; font-weight: bold; color: #1f2937; margin-bottom: 8px; margin-top: 20px; }

        /* Footer */
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 10px; color: #9ca3af; }

        /* Notes */
        .notes { margin-top: 20px; padding: 12px; background: #f9fafb; border-radius: 6px; font-size: 11px; color: #6b7280; }
        .notes-label { font-weight: bold; color: #4b5563; margin-bottom: 4px; }

        /* Garment badge */
        .garment-badge { display: inline-block; padding: 1px 8px; border-radius: 8px; font-size: 9px; font-weight: bold; text-transform: capitalize; }
        .garment-kanzu { background: #dcfce7; color: #15803d; }
        .garment-shirt { background: #dbeafe; color: #1d4ed8; }
        .garment-trouser { background: #fef3c7; color: #92400e; }
        .garment-vest { background: #ede9fe; color: #6d28d9; }

        /* Item type badges */
        .type-badge { display: inline-block; padding: 1px 8px; border-radius: 8px; font-size: 9px; font-weight: bold; }
        .type-custom { background: #dcfce7; color: #15803d; }
        .type-shelf { background: #dbeafe; color: #1d4ed8; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                @if($company->logo_path)
                    <img src="{{ $company->logo_base64 }}" class="logo-img" alt="Logo">
                @endif
                <div class="brand">{{ $company->business_name }}</div>
                @if($company->tagline)
                    <div class="brand-sub">{{ $company->tagline }}</div>
                @endif
                <div class="brand-contact">
                    @if($company->phone) {{ $company->phone }}<br> @endif
                    @if($company->email) {{ $company->email }}<br> @endif
                    @if($company->address) {{ $company->address }} @endif
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">{{ $invoice->type === 'quotation' ? 'QUOTATION' : 'RECEIPT' }}</div>
                <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                <div class="invoice-meta">
                    Date: {{ $invoice->date->format('d M Y') }}<br>
                    @if($invoice->due_date)
                        Due: {{ $invoice->due_date->format('d M Y') }}<br>
                    @endif
                    Status: <span class="status status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
                </div>
            </div>
        </div>

        <!-- Client -->
        <div class="client-section">
            <div class="client-box">
                <div class="section-label">Bill To</div>
                <div class="client-name">{{ $invoice->client->name }}</div>
                <div class="client-detail">{{ $invoice->client->phone }}</div>
                @if($invoice->client->email)
                    <div class="client-detail">{{ $invoice->client->email }}</div>
                @endif
                @if($invoice->client->address)
                    <div class="client-detail">{{ $invoice->client->address }}</div>
                @endif
            </div>
        </div>

        <!-- Line Items -->
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Details</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->lineItems as $item)
                <tr>
                    @if($item->item_type === 'collection')
                        <td><span class="type-badge type-shelf">Shelf</span></td>
                        <td>{{ $item->collection->name ?? $item->description ?? 'Collection Item' }}</td>
                        <td>
                            @if($item->collection?->size) {{ $item->collection->size }} @endif
                            @if($item->collection?->color) &middot; {{ $item->collection->color }} @endif
                        </td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 0) }}</td>
                    @else
                        <td><span class="type-badge type-custom">Custom</span></td>
                        <td>{{ $item->contact->name ?? '—' }}</td>
                        <td>
                            @if($item->measurement)
                                <span class="garment-badge garment-{{ $item->measurement->garment_type }}">{{ $item->measurement->garment_type }}</span>
                            @endif
                            @if($item->fabric) {{ $item->fabric->name }} @endif
                            @if($item->ridhaa_qty > 0 && $item->ridhaa_price > 0)
                                <br><span style="font-size:9px;">{{ $item->ridhaa_name ?: 'Ridhaa' }} x{{ $item->ridhaa_qty }} @ {{ number_format($item->ridhaa_price, 0) }}</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($item->craftsmanship_fee + $item->fabric_cost, 0) }}</td>
                    @endif
                    <td class="text-right font-bold">{{ number_format($item->line_total, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span class="total-label">Subtotal</span>
                <span class="total-value">KES {{ number_format($invoice->subtotal, 0) }}</span>
            </div>
            @if($invoice->discount > 0)
            <div class="total-row">
                <span class="total-label">Discount</span>
                <span class="total-value" style="color: #dc2626;">-KES {{ number_format($invoice->discount, 0) }}</span>
            </div>
            @endif
            @if($invoice->tax > 0)
            <div class="total-row">
                <span class="total-label">Tax</span>
                <span class="total-value">KES {{ number_format($invoice->tax, 0) }}</span>
            </div>
            @endif
            <div class="total-row total-final">
                <span class="total-label" style="font-weight: bold; color: #1f2937;">Total</span>
                <span class="total-value">KES {{ number_format($invoice->total, 0) }}</span>
            </div>
            @if($invoice->amount_paid > 0)
            <div class="total-row" style="margin-top: 8px;">
                <span class="total-label">Amount Paid</span>
                <span class="total-value" style="color: #15803d;">KES {{ number_format($invoice->amount_paid, 0) }}</span>
            </div>
            <div class="total-row">
                <span class="total-label">Balance Due</span>
                <span class="total-value" style="color: {{ $invoice->balance > 0 ? '#dc2626' : '#6b7280' }};">KES {{ number_format($invoice->balance, 0) }}</span>
            </div>
            @endif
        </div>

        <!-- Payments -->
        @if($invoice->payments->count() > 0)
        <div class="payments-title">Payment History</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->payments as $payment)
                <tr>
                    <td>{{ $payment->date->format('d M Y') }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                    <td>{{ $payment->reference ?? '—' }}</td>
                    <td class="text-right font-bold">KES {{ number_format($payment->amount, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Notes -->
        @if($invoice->notes)
        <div class="notes">
            <div class="notes-label">Notes</div>
            {{ $invoice->notes }}
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            @if($company->footer_text)
                {{ $company->footer_text }}<br>
            @else
                Thank you for your business!<br>
            @endif
            {{ $company->business_name }} &middot; {{ now()->format('d M Y, H:i') }}
        </div>
    </div>
</body>
</html>
