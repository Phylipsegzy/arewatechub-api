<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background-color: #f9f9f9;">
    <h2 style="color: #DF2328; text-align: center;">Registration Received</h2>
    <p>Hi {{ $registration->parent_name }},</p>
    <p>Thank you for registering <strong>{{ $registration->child_firstname }} {{ $registration->child_lastname }}</strong> for the <strong>Gen Alpha Future Builders Camp</strong>.</p>
    <div style="background: #fff; border: 1px solid #eee; border-radius: 6px; padding: 15px; margin: 20px 0;">
        <p style="margin: 0 0 8px;"><strong>Compulsory registration fee:</strong> ₦{{ number_format($registration->registration_amount, 2) }}</p>
        <p style="margin: 0;">This secures the slot — please log in to your dashboard and pay from your wallet to confirm.</p>
    </div>
    <p>An optional VIP upgrade (+₦{{ number_format($registration->vip_amount, 2) }}, includes an ArewaTecHub-branded laptop) can be added any time after the registration fee is paid.</p>
    <p style="margin-top: 20px;">Thank you,<br><span style="color: #DF2328;">ArewaTecHub Team</span></p>
</div>
