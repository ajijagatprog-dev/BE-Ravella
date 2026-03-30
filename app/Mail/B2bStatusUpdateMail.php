<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bStatusUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $newStatus;

    public function __construct(User $user, string $newStatus)
    {
        $this->user = $user;
        $this->newStatus = $newStatus;
    }

    public function envelope(): Envelope
    {
        $subject = $this->newStatus === 'approved'
            ? '[Ravella] Selamat! Akun B2B Anda Telah Disetujui'
            : '[Ravella] Informasi Status Pendaftaran B2B Anda';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.b2b_status_update',
        );
    }
}
