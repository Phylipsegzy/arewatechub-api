<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 13px; line-height: 1.5; margin:0; padding:0; }
    .page { position:relative; padding: 32px; }
    .watermark { position:absolute; top:220px; left:50%; transform:translateX(-50%); opacity:0.06; width:340px; z-index:0; }
    .content { position:relative; z-index:2; }
    .header { background: linear-gradient(135deg, #B91C1C, #7C0A0A); color:#fff; padding:22px 24px; border-radius:14px; }
    .header-table { width:100%; border-collapse:collapse; }
    .logo { width:85px; }
    .brand-title { font-size:22px; font-weight:bold; margin:0 0 4px; }
    .brand-sub { font-size:11px; margin:0; opacity:0.9; }
    .receipt-box { text-align:right; }
    .receipt-label { font-size:11px; text-transform:uppercase; letter-spacing:1px; opacity:0.9; }
    .receipt-title { font-size:20px; font-weight:bold; margin-top:4px; }
    .card { border:1px solid #F3D2D2; border-radius:14px; margin-top:20px; overflow:hidden; }
    .card-head { background:#FEF2F2; padding:12px 18px; font-weight:bold; color:#B91C1C; border-bottom:1px solid #F3D2D2; }
    .card-body { padding:16px 18px; }
    table.meta { width:100%; border-collapse:collapse; }
    table.meta td { padding:6px 0; vertical-align:top; }
    .meta-label { color:#6B7280; width:38%; font-weight:bold; }
    .amount-box { margin-top:16px; text-align:right; }
    .amount-paid { display:inline-block; background:#B91C1C; color:#fff; padding:12px 18px; border-radius:10px; font-size:17px; font-weight:bold; }
    .status { display:inline-block; padding:6px 12px; border-radius:999px; font-size:11px; font-weight:bold; text-transform:capitalize; }
    .status-successful { background:#DCFCE7; color:#166534; }
    .status-pending { background:#FEF9C3; color:#92400E; }
    .status-failed { background:#FEE2E2; color:#991B1B; }
    .footer { margin-top:24px; font-size:11px; color:#6B7280; text-align:center; }
</style>
</head>
<body>
<div class="page">
    @if($logoSrc)<img class="watermark" src="{{ $logoSrc }}">@endif
    <div class="content">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td width="90">@if($logoSrc)<img class="logo" src="{{ $logoSrc }}">@endif</td>
                    <td>
                        <div class="brand-title">ArewaTecHub</div>
                        <p class="brand-sub">Wallet Funding — Receipt</p>
                    </td>
                    <td class="receipt-box">
                        <div class="receipt-label">Reference</div>
                        <div class="receipt-title" style="font-size:14px;">{{ $transaction->reference }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card">
            <div class="card-head">Funding Information</div>
            <div class="card-body">
                <table class="meta">
                    <tr><td class="meta-label">Customer:</td><td>{{ $transaction->customer->firstname }} {{ $transaction->customer->lastname }}</td></tr>
                    <tr><td class="meta-label">Email:</td><td>{{ $transaction->customer->email }}</td></tr>
                    <tr><td class="meta-label">Source:</td><td>{{ str_replace('_', ' ', ucfirst($transaction->source)) }}</td></tr>
                    <tr><td class="meta-label">Date:</td><td>{{ $transaction->created_at->format('F j, Y g:i A') }}</td></tr>
                    <tr><td class="meta-label">Status:</td><td><span class="status status-{{ $transaction->status }}">{{ $transaction->status }}</span></td></tr>
                    <tr><td class="meta-label">Balance After:</td><td>&#8358;{{ number_format($transaction->balance_after, 2) }}</td></tr>
                </table>
            </div>
        </div>

        <div class="amount-box">
            <div class="amount-paid">Amount Funded: &#8358;{{ number_format($transaction->amount, 2) }}</div>
        </div>

        <div class="footer">
            ArewaTecHub &middot; This receipt confirms a successful wallet funding.
        </div>
    </div>
</div>
</body>
</html>
