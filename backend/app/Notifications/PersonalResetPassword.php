<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class PersonalResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], '', '&', PHP_QUERY_RFC3986);

        return (new MailMessage)
            ->from((string) config('mail.from.address'), __('mail.sender_name'))
            ->subject(__('mail.reset.subject'))
            ->view([
                'html' => 'emails.personal-transactional',
                'text' => 'emails.personal-transactional-text',
            ], [
                'preheader' => __('mail.reset.preheader'),
                'title' => __('mail.reset.title'),
                'copy' => __('mail.reset.copy'),
                'actionLabel' => __('mail.reset.action'),
                'actionUrl' => $resetUrl,
                'notice' => __('mail.reset.notice', [
                    'count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ]),
            ]);
    }
}
