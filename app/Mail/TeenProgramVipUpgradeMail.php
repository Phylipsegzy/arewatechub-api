<?php

namespace App\Mail;

use App\Models\TeenProgramRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeenProgramVipUpgradeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TeenProgramRegistration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Future Builders Camp — {$this->registration->child_firstname} is Now VIP!");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.teen-program-vip-upgrade');
    }
}
