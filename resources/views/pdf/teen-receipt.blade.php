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
    .congrats { text-align:center; margin-top:20px; }
    .congrats h2 { font-size:18px; color:#B91C1C; margin:0 0 2px; }
    .congrats p { color:#6B7280; margin:0; font-size:12px; }
    .card { border:1px solid #F3D2D2; border-radius:14px; margin-top:20px; overflow:hidden; }
    .card-head { background:#FEF2F2; padding:12px 18px; font-weight:bold; color:#B91C1C; border-bottom:1px solid #F3D2D2; }
    .card-body { padding:16px 18px; }
    table.meta { width:100%; border-collapse:collapse; }
    table.meta td { padding:6px 0; vertical-align:top; }
    .meta-label { color:#6B7280; width:38%; font-weight:bold; }
    table.details { width:100%; border-collapse:collapse; }
    table.details th, table.details td { border:1px solid #F3D2D2; padding:10px 12px; text-align:left; }
    table.details th { background:#FEF2F2; color:#B91C1C; }
    .amount-box { margin-top:16px; text-align:right; }
    .amount-paid { display:inline-block; background:#B91C1C; color:#fff; padding:12px 18px; border-radius:10px; font-size:17px; font-weight:bold; }
    .status { display:inline-block; padding:6px 12px; border-radius:999px; background:#DCFCE7; color:#166534; font-size:11px; font-weight:bold; }
    .action-box { margin-top:18px; padding:14px 16px; background:#111827; color:#fff; border-radius:12px; font-size:12px; }
    .action-box strong { color:#FACC15; }
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
                        <p class="brand-sub">Gen Alpha Future Builders Camp — Payment Receipt</p>
                    </td>
                    <td class="receipt-box">
                        <div class="receipt-label">Receipt</div>
                        <div class="receipt-title">{{ $receiptNo }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="congrats">
            <h2>Congratulations, {{ $childName }}&rsquo;s slot is confirmed! &#127881;</h2>
            <p>Thank you for completing payment for the Future Builders Camp{{ $isVip ? ' — VIP' : '' }}</p>
        </div>

        <div class="card">
            <div class="card-head">Payment Information</div>
            <div class="card-body">
                <table class="meta">
                    <tr><td class="meta-label">Child&rsquo;s Name:</td><td>{{ $childName }}</td></tr>
                    <tr><td class="meta-label">Parent / Guardian:</td><td>{{ $parentName }}</td></tr>
                    <tr><td class="meta-label">Email Address:</td><td>{{ $email }}</td></tr>
                    <tr><td class="meta-label">Most Recent Payment:</td><td>{{ $latestDate }}</td></tr>
                    <tr><td class="meta-label">Payment Method:</td><td>Wallet</td></tr>
                    <tr><td class="meta-label">Status:</td><td><span class="status">PAID</span></td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head">Payments Made</div>
            <div class="card-body">
                <table class="details">
                    <thead>
                        <tr><th>Program</th><th>Item</th><th>Reference</th><th>Amount</th></tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $p)
                        <tr>
                            <td>Gen Alpha Future Builders Camp</td>
                            <td>{{ $p->payment_type === 'vip' ? 'VIP Upgrade' : 'Registration / Acceptance Fee' }}</td>
                            <td>{{ $p->reference }}</td>
                            <td>&#8358;{{ number_format($p->amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="amount-box">
                    <div class="amount-paid">Total Paid: &#8358;{{ number_format($totalPaid, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="action-box">
            <strong>Next step:</strong> Please bring a printed copy of this receipt along with your
            Admission Letter to the ArewaTecHub office for proper documentation before the camp begins.
        </div>

        <div class="footer">
            Thank you for choosing ArewaTecHub. This receipt confirms successful payment for the
            Gen Alpha Future Builders Camp registration.
        </div>
    </div>
</div>
</body>
</html>
