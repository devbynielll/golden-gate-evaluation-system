<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentAccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Golden Gate Colleges Evaluation Account',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.students.account-created',
            with: [
                'name' => $this->student->fullName(),
                'idNumber' => $this->student->student_number,
                'password' => $this->password,
            ],
        );
    }
}
