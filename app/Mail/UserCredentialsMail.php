<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Deliberately NOT queued (no ShouldQueue): a queued mail would store the plain password
 * in the jobs table. Sending directly also lets the controller roll back if the email fails.
 */
class UserCredentialsMail extends Mailable
{
    public function __construct(
        public User $user,
        public string $plainPassword,
        public bool $isReset = false,
        public string $userType = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isReset
                ? config('app.name') . ': your password was reset'
                : 'Your ' . config('app.name') . ' account is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user-credentials',
            with: [
                'name'     => $this->user->name,
                'email'    => $this->user->email,
                'password' => $this->plainPassword,
                'isReset'  => $this->isReset,
                'userType' => $this->userType,
                'loginUrl' => route('login'),
            ],
        );
    }
}