<?php
namespace App\Mail;
use App\Models\AcademyEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewAcademyEnrollmentMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public AcademyEnrollment $enrollment) {}
    public function envelope(): Envelope { return new Envelope(subject: 'New Cohort Programme Enrollment'); }
    public function content(): Content { return new Content(view: 'emails.admin-new-cohort-enrollment'); }
}
