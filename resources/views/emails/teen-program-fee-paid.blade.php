<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background-color: #f9f9f9;">
    <h2 style="color: #DF2328; text-align: center;">Slot Confirmed! 🎉</h2>
    <p>Hi {{ $registration->parent_name }},</p>
    <p>₦{{ number_format($registration->registration_amount, 2) }} has been deducted from your wallet. <strong>{{ $registration->child_firstname }} {{ $registration->child_lastname }}'s</strong> slot for the Gen Alpha Future Builders Camp is now confirmed.</p>
    <p>Log in to your dashboard to download your admission letter and receipt — print both and bring them to ArewaTecHub for documentation.</p>
    <p style="margin-top: 20px;">Thank you,<br><span style="color: #DF2328;">ArewaTecHub Team</span></p>
</div>
