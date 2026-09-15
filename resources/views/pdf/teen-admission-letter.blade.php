<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 13px; line-height: 1.6; margin:0; padding:0; }
    .page { padding: 34px; }
    .header { background: linear-gradient(135deg, #B91C1C, #7C0A0A); color:#fff; padding:22px 26px; border-radius:14px; }
    .header-table { width:100%; border-collapse:collapse; }
    .logo { width:80px; }
    .brand-title { font-size:22px; font-weight:bold; margin:0; }
    .brand-sub { font-size:11px; margin:2px 0 0; opacity:0.9; }
    .letter-box { text-align:right; }
    .letter-label { font-size:11px; text-transform:uppercase; letter-spacing:1px; opacity:0.9; }
    .letter-title { font-size:18px; font-weight:bold; margin-top:4px; }
    .congrats { text-align:center; margin-top:26px; }
    .congrats h1 { font-size:22px; color:#B91C1C; margin:0 0 4px; }
    .congrats p { color:#6B7280; margin:0; font-size:13px; }
    .card { border:1px solid #F3D2D2; border-radius:14px; margin-top:22px; overflow:hidden; }
    .card-head { background:#FEF2F2; padding:12px 18px; font-weight:bold; color:#B91C1C; border-bottom:1px solid #F3D2D2; }
    .card-body { padding:16px 18px; }
    table.meta { width:100%; border-collapse:collapse; }
    table.meta td { padding:6px 0; vertical-align:top; }
    .meta-label { color:#6B7280; width:38%; font-weight:bold; }
    ul.checklist { margin:6px 0 0; padding-left:18px; }
    ul.checklist li { margin-bottom:6px; }
    .badge { display:inline-block; padding:5px 12px; border-radius:999px; font-size:11px; font-weight:bold; }
    .badge-vip { background:#FEF9C3; color:#CA8A04; }
    .badge-standard { background:#FEE2E2; color:#B91C1C; }
    .action-box { margin-top:18px; padding:14px 16px; background:#111827; color:#fff; border-radius:12px; font-size:12px; }
    .action-box strong { color:#FACC15; }
    .footer { margin-top:26px; font-size:11px; color:#6B7280; text-align:center; }
    .signature { margin-top:30px; }
</style>
</head>
<body>
<div class="page">

    <div class="header">
        <table class="header-table">
            <tr>
                <td width="90">@if($logoSrc)<img class="logo" src="{{ $logoSrc }}">@endif</td>
                <td>
                    <div class="brand-title">ArewaTecHub</div>
                    <p class="brand-sub">Gen Alpha Future Builders Camp</p>
                </td>
                <td class="letter-box">
                    <div class="letter-label">Admission No.</div>
                    <div class="letter-title">{{ $admissionNo }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="congrats">
        <h1>Congratulations, {{ $childName }}! &#127881;</h1>
        <p>You have been admitted into the Gen Alpha Future Builders Camp</p>
    </div>

    <div class="card">
        <div class="card-head">Admission Details</div>
        <div class="card-body">
            <table class="meta">
                <tr><td class="meta-label">Child&rsquo;s Name:</td><td>{{ $childName }}</td></tr>
                <tr><td class="meta-label">Age:</td><td>{{ $registration->child_age }}</td></tr>
                <tr><td class="meta-label">Parent / Guardian:</td><td>{{ $parentName }}</td></tr>
                <tr><td class="meta-label">Package:</td><td><span class="badge badge-{{ strtolower($category) }}">{{ $category }}</span></td></tr>
                <tr><td class="meta-label">Commitment Fee Paid:</td><td>&#8358;{{ number_format($amount) }}</td></tr>
                <tr><td class="meta-label">Date Issued:</td><td>{{ $issuedDate }}</td></tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">What Your Child Will Experience</div>
        <div class="card-body">
            <p>The Gen Alpha Future Builders Camp is an intensive youth innovation experience built around four
               pillars: <strong>Think, Create, Build, and Lead.</strong> Rather than just teaching technology, we
               help children think independently, solve problems creatively, build real-world projects, and grow
               into confident leaders.</p>
            <ul class="checklist">
                <li>Hands-on, project-based learning across the full camp curriculum</li>
                <li>Certificate of completion and a digital portfolio of projects</li>
                <li>Mentorship and guidance throughout the program</li>
                @if($isVip)
                <li><strong>Branded laptop — theirs to keep</strong></li>
                <li>Premium camp kit and branded materials</li>
                <li>Unlimited on-site internet access</li>
                <li>Weekly personal mentoring</li>
                <li>Access to AI learning tools (ChatGPT, Gemini, Claude)</li>
                <li>Priority networking and career guidance</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-head">What to Bring</div>
        <div class="card-body">
            <ul class="checklist">
                <li>A printed copy of this Admission Letter</li>
                <li>A printed copy of your Payment Receipt</li>
                <li>A notebook and writing materials</li>
                <li>A positive, curious attitude!</li>
            </ul>
        </div>
    </div>

    <div class="action-box">
        <strong>Next step:</strong> Please bring a printed copy of both this Admission Letter and your
        Payment Receipt to the ArewaTecHub office for proper documentation before the camp begins.
    </div>

    <div class="signature">
        <p>We are excited to welcome {{ $childName }} and look forward to seeing what they
           will create, build, and lead.</p>
        <p style="margin-top:20px;">Warm regards,<br><strong>ArewaTecHub — Gen Alpha Future Builders Camp Team</strong></p>
    </div>

    <div class="footer">
        ArewaTecHub &middot; 1st Floor, ArewaTecHub Building, Gwarzo Road, Kano, Nigeria &middot; info@arewatechub.com.ng
    </div>

</div>
</body>
</html>
