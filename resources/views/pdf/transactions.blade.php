<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Transaction Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0;
            font-size: 10px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th,
        table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .summary-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .summary-row {
            display: table-row;
        }
        .summary-label {
            display: table-cell;
            width: 40%;
            font-weight: bold;
            padding: 5px;
        }
        .summary-value {
            display: table-cell;
            width: 60%;
            padding: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ strtoupper(config('school.name')) }}</h1>
        <p>
            Main Campus: {{ config('school.main_address') }} |
            {{ config('school.annex_address') }}
        </p>
        <p>
            Website: {{ config('school.website') }} &nbsp;|&nbsp;
            Hotline: {{ config('school.hotline') }} &nbsp;|&nbsp;
            CP: {{ config('school.mobile') }}
        </p>
        <p style="margin-top: 8px; font-size: 11px; font-weight: bold;">
            Transaction Report
        </p>
        <p>Report Generated: {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    <div class="section">
        <div class="section-title">Transaction Summary</div>
        <div class="summary-grid">
            <div class="summary-row">
                <div class="summary-label">Total Transactions:</div>
                <div class="summary-value">{{ $transactions->count() }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-label">Total Amount:</div>
                <div class="summary-value">₱{{ number_format($transactions->sum('amount'), 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-label">Charges:</div>
                <div class="summary-value">₱{{ number_format($transactions->where('kind', 'charge')->sum('amount'), 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-label">Payments:</div>
                <div class="summary-value">₱{{ number_format($transactions->where('kind', 'payment')->where('status', 'paid')->sum('amount'), 2) }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">All Transactions</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Kind</th>
                    <th>Status</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->created_at->format('M d, Y') }}</td>
                        <td>{{ $transaction->reference ?? '-' }}</td>
                        <td>{{ $transaction->type ?? '-' }}</td>
                        <td>{{ $transaction->category ?? '-' }}</td>
                        <td style="font-size: 10px;">
                            @if($transaction->meta && isset($transaction->meta['description']))
                                {{ $transaction->meta['description'] }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-center">
                            @if($transaction->kind === 'charge')
                                <span style="background-color: #ffcccc; padding: 2px 5px; border-radius: 3px;">Charge</span>
                            @else
                                <span style="background-color: #ccffcc; padding: 2px 5px; border-radius: 3px;">Payment</span>
                            @endif
                        </td>
                        <td>
                            @if($transaction->status === 'pending')
                                <span style="background-color: #fff3cd; padding: 2px 5px; border-radius: 3px;">Pending</span>
                            @elseif($transaction->status === 'paid')
                                <span style="background-color: #d4edda; padding: 2px 5px; border-radius: 3px;">Paid</span>
                            @else
                                {{ ucfirst($transaction->status) }}
                            @endif
                        </td>
                        <td class="text-right">₱{{ number_format($transaction->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No transactions found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Transaction Summary by Kind</div>
        <table>
            <thead>
                <tr>
                    <th>Kind</th>
                    <th class="text-center">Count</th>
                    <th class="text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Charges</td>
                    <td class="text-center">{{ $transactions->where('kind', 'charge')->count() }}</td>
                    <td class="text-right">₱{{ number_format($transactions->where('kind', 'charge')->sum('amount'), 2) }}</td>
                </tr>
                <tr>
                    <td>Payments (Paid Only)</td>
                    <td class="text-center">{{ $transactions->where('kind', 'payment')->where('status', 'paid')->count() }}</td>
                    <td class="text-right">₱{{ number_format($transactions->where('kind', 'payment')->where('status', 'paid')->sum('amount'), 2) }}</td>
                </tr>
                <tr class="total-row" style="background-color: #e0e0e0;">
                    <td>Net Amount</td>
                    <td class="text-center">{{ $transactions->count() }}</td>
                    <td class="text-right">
                        ₱{{ number_format(
                            $transactions->where('kind', 'charge')->sum('amount') - 
                            $transactions->where('kind', 'payment')->where('status', 'paid')->sum('amount'), 
                            2
                        ) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>Generated on {{ now()->format('F d, Y h:i A') }}</p>
        <p>This is a computer-generated document. No signature required.</p>
    </div>
</body>
</html>
