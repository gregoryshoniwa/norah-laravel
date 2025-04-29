@extends('layouts.pdf')

@section('title', 'Transaction Receipt')

@section('styles')
    body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .receipt-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: #010647;
            text-align: center;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #010647;
        }
        .details {
            width: 100%;
            border-collapse: collapse;
        }
        .details td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .details td:first-child {
            font-weight: bold;
            width: 200px;
        }
        .amount {
            font-size: 18px;
            font-weight: bold;
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .status.completed {
            background-color: #e6f4ea;
            color: #1e7e34;
            border: 1px solid #1e7e34;
        }
        .status.failed {
            background-color: #fce8e8;
            color: #dc3545;
            border: 1px solid #dc3545;
        }
        .status.pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #856404;
        }
        .verification-code {
            margin-top: 30px;
            text-align: center;
            padding: 10px;
            border-top: 1px dashed #ddd;
            color: #666;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .company-info {
            margin-top: 20px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div style="font-size: 36px; font-weight: bold; margin-bottom: 10px; color: #010647; letter-spacing: 2px;">
                NPG
            </div>
            <div style="font-size: 18px; color: #666; margin-bottom: 5px;">
                Norah Payment Gateway
            </div>
            <div style="font-size: 12px; color: #888;">
                Transaction #{{ $transaction_id }}
            </div>
        </div>

        <h1 class="receipt-title">Transaction Receipt</h1>

        <div class="section">
            <div class="section-title">Transaction Details</div>
            <table class="details">
                <tr>
                    <td>Transaction ID</td>
                    <td>#{{ $transaction_id }}</td>
                </tr>
                <tr>
                    <td>Date</td>
                    <td>{{ $date }}</td>
                </tr>
                <tr>
                    <td>Reference</td>
                    <td>{{ $reference }}</td>
                </tr>
                <tr>
                    <td>Type</td>
                    <td>{{ $type }}</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td>
                        <span class="status {{ strtolower($status) }}">
                            {{ $status }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Amount Details</div>
            <table class="details">
                <tr>
                    <td>Amount</td>
                    <td class="amount">{{ $amount }}</td>
                </tr>
                @if(isset($charge) && $charge > 0)
                <tr>
                    <td>Service Charge</td>
                    <td>{{ $charge }}</td>
                </tr>
                <tr>
                    <td>Total Amount</td>
                    <td class="amount">{{ $total_amount }}</td>
                </tr>
                @endif
            </table>
        </div>

        <div class="section">
            <div class="section-title">Customer Information</div>
            <table class="details">
                <tr>
                    <td>Customer Name</td>
                    <td>{{ $customer }}</td>
                </tr>
                @if(isset($email))
                <tr>
                    <td>Email</td>
                    <td>{{ $email }}</td>
                </tr>
                @endif
            </table>
        </div>

        <div class="verification-code">
            <div style="font-size: 14px; margin-bottom: 5px;">Verification Code</div>
            <div style="font-family: monospace; font-size: 16px; font-weight: bold; letter-spacing: 2px;">
                {{ strtoupper(substr(md5($transaction_id . $reference), 0, 16)) }}
            </div>
            <div style="font-size: 12px; margin-top: 5px; color: #888;">
                Verify this receipt at verify.npg.africa
            </div>
        </div>

        <div class="footer">
            <div>Thank you for using Norah Payment Gateway</div>
            <div class="company-info">
                NPG Limited<br>
                support@npg.africa<br>
                +263 772 123 456
            </div>
        </div>
    </div>
</body>
</html>
