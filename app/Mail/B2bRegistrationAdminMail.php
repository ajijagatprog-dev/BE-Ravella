<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bRegistrationAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $applicant;

    public function __construct(User $applicant)
    {
        $this->applicant = $applicant;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Ravella] Pendaftaran B2B Baru: ' . $this->applicant->company_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.b2b_registration_admin',
        );
    }
}
