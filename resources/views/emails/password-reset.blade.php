<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background-color: #f9f9f9;">
    <h2 style="color: #DF2328; text-align: center;">Password Reset Request</h2>
    <p>Hi <strong>{{ $firstname }}</strong>,</p>
    <p>We received a request to reset your password. Click the button below to choose a new one:</p>
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $resetLink }}"
           style="background-color: #DF2328; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">
           Reset Password
        </a>
    </div>
    <p>If you did not request this, you can safely ignore this email.</p>
    <p style="margin-top: 20px;">Thank you,<br><span style="color: #DF2328;">ArewaTecHub Team</span></p>
    <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
    <p style="font-size: 12px; color: #777; text-align: center;">This link expires in 1 hour.</p>
</div>
