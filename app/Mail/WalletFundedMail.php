<?php

namespace App\Mail;

use App\Models\WalletTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WalletFundedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WalletTransaction $transaction) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Wallet Has Been Funded');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.wallet-funded');
    }
}
