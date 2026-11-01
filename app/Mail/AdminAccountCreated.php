<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminAccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $password,
        public bool $reset = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reset
                ? 'Your Golden Gate Colleges Evaluation Password Was Reset'
                : 'Your Golden Gate Colleges Evaluation Admin Account',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admins.account-created',
            with: [
                'name' => $this->user->name,
                'idNumber' => $this->user->id_number,
                'password' => $this->password,
                'reset' => $this->reset,
                'scope' => $this->user->isDepartmentAdmin() ? 'all departments' : 'all offices',
            ],
        );
    }
}
