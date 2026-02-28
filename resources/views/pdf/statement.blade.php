<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Statement - {{ $client->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #1f2937; line-height: 1.5; }
        .container { max-width: 700px; margin: 0 auto; padding: 30px; }

        .header { display: table; width: 100%; margin-bottom: 30px; border-bottom: 2px solid #22c55e; padding-bottom: 20px; }
        .header-left { display: table-cell; vertical-align: top; width: 60%; }
        .header-right { display: table-cell; vertical-align: top; width: 40%; text-align: right; }
        .brand { font-size: 24px; font-weight: bold; color: #22c55e; margin-bottom: 4px; }
        .brand-sub { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .brand-contact { font-size: 10px; color: #6b7280; margin-top: 6px; line-height: 1.6; }
        .logo-img { max-height: 60px; max-width: 160px; margin-bottom: 8px; }
        .doc-title { font-size: 20px; font-weight: bold; color: #1f2937; }
        .doc-meta { font-size: 10px; color: #6b7280; margin-top: 8px; }

        .client-section { margin-bottom: 20px; }
        .section-label { font-size: 9px; text-transform: uppercase; color: #9ca3af; letter-spacing: 1px; margin-bottom: 4px; font-weight: bold; }
        .client-name { font-size: 14px; font-weight: bold; color: #1f2937; }
        .client-detail { font-size: 11px; color: #6b7280; }

        /* Summary boxes */
        .summary { display: table; width: 100%; margin-bottom: 24px; }
        .summary-box { display: table-cell; width: 33.33%; padding: 10px 12px; text-align: center; border: 1px solid #e5e7eb; border-radius: 4px; }
        .summary-box + .summary-box { margin-left: 8px; }
        .summary-label { font-size: 9px; text-transform: uppercase; color: #9ca3af; letter-spacing: 1px; }
        .summary-value { font-size: 16px; font-weight: bold; color: #1f2937; margin-top: 2px; }
        .summary-value.green { color: #15803d; }
        .summary-value.red { color: #dc2626; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f9fafb; padding: 8px 12px; text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        td { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        .status { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .status-draft { background: #f3f4f6; color: #6b7280; }
        .status-issued { background: #dbeafe; color: #1d4ed8; }
        .status-paid { background: #dcfce7; color: #15803d; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        .status-partial { background: #fef3c7; color: #92400e; }
        .status-voided { background: #f3f4f6; color: #9ca3af; }

        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 10px; color: #9ca3af; }
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
                <div class="doc-title">ACCOUNT STATEMENT</div>
                <div class="doc-meta">
                    Generated: {{ now()->format('d M Y') }}<br>
                    All Invoices &amp; Payments
                </div>
            </div>
        </div>

        <!-- Client -->
        <div class="client-section">
            <div class="section-label">Statement For</div>
            <div class="client-name">{{ $client->name }}</div>
            @if($client->phone) <div class="client-detail">{{ $client->phone }}</div> @endif
            @if($client->email) <div class="client-detail">{{ $client->email }}</div> @endif
        </div>

        <!-- Summary -->
        <div class="summary">
            <div class="summary-box">
                <div class="summary-label">Total Invoiced</div>
                <div class="summary-value">KES {{ number_format($totalInvoiced, 0) }}</div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Total Paid</div>
                <div class="summary-value green">KES {{ number_format($totalPaid, 0) }}</div>
            </div>
            <div class="summary-box">
                <div class="summary-label">Balance Due</div>
                <div class="summary-value {{ $totalBalance > 0 ? 'red' : '' }}">KES {{ number_format($totalBalance, 0) }}</div>
            </div>
        </div>

        <!-- Invoices -->
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Paid</th>
                    <th class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($client->invoices as $inv)
                <tr>
                    <td class="font-bold">{{ $inv->invoice_number }}</td>
                    <td>{{ $inv->date->format('d M Y') }}</td>
                    <td>{{ $inv->due_date ? $inv->due_date->format('d M Y') : '—' }}</td>
                    <td><span class="status status-{{ $inv->status }}">{{ ucfirst($inv->status) }}</span></td>
                    <td class="text-right">KES {{ number_format($inv->total, 0) }}</td>
                    <td class="text-right" style="color: #15803d;">KES {{ number_format($inv->amount_paid, 0) }}</td>
                    <td class="text-right {{ $inv->balance > 0 ? 'font-bold' : '' }}" style="color: {{ $inv->balance > 0 ? '#dc2626' : '#6b7280' }};">
                        KES {{ number_format($inv->balance, 0) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: bold;">
                    <td colspan="4" style="padding: 10px 12px; font-size: 11px;">TOTALS</td>
                    <td class="text-right" style="padding: 10px 12px;">KES {{ number_format($totalInvoiced, 0) }}</td>
                    <td class="text-right" style="padding: 10px 12px; color: #15803d;">KES {{ number_format($totalPaid, 0) }}</td>
                    <td class="text-right" style="padding: 10px 12px; color: {{ $totalBalance > 0 ? '#dc2626' : '#6b7280' }};">KES {{ number_format($totalBalance, 0) }}</td>
                </tr>
            </tfoot>
        </table>

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
