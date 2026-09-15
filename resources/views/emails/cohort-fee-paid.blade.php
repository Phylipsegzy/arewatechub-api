<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background-color: #f9f9f9;">
    <h2 style="color: #DF2328; text-align: center;">Payment Confirmed! 🎉</h2>
    <p>Hi {{ $enrollment->customer->firstname }},</p>
    <p>₦{{ number_format($enrollment->amount_paid, 2) }} has been deducted from your wallet. Your slot in <strong>{{ $enrollment->intake->name }}</strong> ({{ $enrollment->track_selected }}) is now confirmed.</p>
    <p style="margin-top: 20px;">Thank you,<br><span style="color: #DF2328;">ArewaTecHub Team</span></p>
</div>
