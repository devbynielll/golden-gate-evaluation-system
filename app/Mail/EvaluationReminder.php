<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EvaluationReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public string $evaluateeLabel,
        public array $pending = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: Pending Evaluation at Golden Gate Colleges',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.evaluations.reminder',
            with: [
                'name' => $this->student->fullName(),
                'evaluateeLabel' => $this->evaluateeLabel,
                'pending' => $this->pending,
            ],
        );
    }
}
