<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background-color: #f9f9f9;">
    <h2 style="color: #DF2328; text-align: center;">Enrollment Received</h2>
    <p>Hi {{ $enrollment->customer->firstname }},</p>
    <p>Thank you for enrolling in <strong>{{ $enrollment->intake->name }}</strong> ({{ $enrollment->track_selected }}) for the Digital Academy Cohort Programme.</p>
    <div style="background: #fff; border: 1px solid #eee; border-radius: 6px; padding: 15px; margin: 20px 0;">
        <p style="margin: 0 0 8px;"><strong>Bootcamp/Non-Bootcamp fee:</strong> ₦{{ number_format($enrollment->bootcamp_fee, 2) }}</p>
        <p style="margin: 0 0 8px;"><strong>Tuition fee:</strong> ₦{{ number_format($enrollment->tuition_fee, 2) }}{{ $enrollment->tuition_tier === 'discounted' ? ' (50% discount applied)' : '' }}</p>
        <p style="margin: 0;"><strong>Total due:</strong> ₦{{ number_format($enrollment->amount_due, 2) }}</p>
    </div>
    <p>Log in to your dashboard and pay from your wallet to secure your slot in {{ $enrollment->intake->name }}.</p>
    <p style="margin-top: 20px;">Thank you,<br><span style="color: #DF2328;">ArewaTecHub Team</span></p>
</div>
