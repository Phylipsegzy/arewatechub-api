<?php

namespace App\Mail;

use App\Models\TeenProgramRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeenProgramRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TeenProgramRegistration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Future Builders Camp — Registration Received');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.teen-program-registered');
    }
}
