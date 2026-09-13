<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends ResetPasswordNotification
{
    /**
     * The user instance.
     *
     * @var \App\Models\User|null
     */
    protected $user;

    /**
     * Create a new notification instance.
     *
     * @param  string  $token
     * @param  \App\Models\User|null  $user
     * @return void
     */
    public function __construct($token, $user = null)
    {
        parent::__construct($token);
        $this->user = $user;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        // Use the user passed in constructor, or the notifiable
        $user = $this->user ?? $notifiable;

        // Use pending_email if exists, otherwise use current email
        $email = $user->pending_email ?? $user->email;

        // Generate reset URL with the appropriate email
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $email,
        ], false));

        return (new MailMessage)
            ->subject('Reset Password - '.config('app.name', 'Boilerplate'))
            ->view('emails.reset-password', [
                'resetUrl' => $resetUrl,
                'userName' => $user->name,
            ]);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
