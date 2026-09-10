<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
    <h2 style="color: #DF2328;">How was the workspace today?</h2>
    <p>Hi {{ $booking->customer->firstname }},</p>
    <p>Thanks for using {{ $booking->room->name }} at ArewaTecHub. We'd love a minute of your feedback — what worked, and what could we improve?</p>
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $surveyLink }}"
           style="background-color: #DF2328; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">
           Rate Your Experience
        </a>
    </div>
    <p>Thank you,<br><span style="color: #DF2328;">ArewaTecHub Team</span></p>
</div>
