<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class PersonalVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.verify.subject'))
            ->view([
                'html' => 'emails.personal-transactional',
                'text' => 'emails.personal-transactional-text',
            ], [
                'preheader' => __('mail.verify.preheader'),
                'title' => __('mail.verify.title'),
                'copy' => __('mail.verify.copy'),
                'actionLabel' => __('mail.verify.action'),
                'actionUrl' => $this->verificationUrl($notifiable),
                'notice' => __('mail.verify.notice'),
            ]);
    }
}
