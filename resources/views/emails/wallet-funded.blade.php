<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
    <h2 style="color: #DF2328;">Wallet Funded</h2>
    <p>Hi {{ $transaction->customer->firstname }},</p>
    <p>Your bank transfer of <strong>₦{{ number_format($transaction->amount, 2) }}</strong> has been confirmed and credited to your wallet.</p>
    <p>New wallet balance: <strong>₦{{ number_format($transaction->customer->wallet_balance, 2) }}</strong></p>
    <p>Thank you,<br><span style="color: #DF2328;">ArewaTecHub Team</span></p>
</div>
