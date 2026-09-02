<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PersonalWelcome extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->from((string) config('mail.from.address'), __('mail.sender_name'))
            ->subject(__('mail.welcome.subject'))
            ->view([
                'html' => 'emails.personal-transactional',
                'text' => 'emails.personal-transactional-text',
            ], [
                'preheader' => __('mail.welcome.preheader'),
                'title' => __('mail.welcome.title'),
                'copy' => __('mail.welcome.copy', ['name' => $notifiable->name]),
                'actionLabel' => __('mail.welcome.action'),
                'actionUrl' => rtrim((string) config('app.frontend_url'), '/'),
                'notice' => __('mail.welcome.notice'),
            ]);
    }
}
