<?php

namespace App\Mail;

use App\Models\TeenProgramRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewTeenRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TeenProgramRegistration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New Future Builders Camp Registration');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-new-teen-registration');
    }
}
