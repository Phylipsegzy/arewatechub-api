<?php
namespace App\Mail;
use App\Models\CohortEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CohortFeePaidMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public CohortEnrollment $enrollment) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Digital Academy Cohort — Payment Confirmed'); }
    public function content(): Content { return new Content(view: 'emails.cohort-fee-paid'); }
}
