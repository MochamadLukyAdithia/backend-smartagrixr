<?php

namespace App\Notifications;
 
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
 
class VerifyEmailNotification extends Notification
{
    public function __construct(
        private string $token,
        private string $userName,
    ) {}
 
    public function via($notifiable): array
    {
        return ['mail'];
    }
 
    public function toMail($notifiable): MailMessage
    {
        $verifyUrl = config('app.frontend_url')
            . '/auth/verify-email?token='
            . $this->token
            . '&email='
            . urlencode($notifiable->email);
 
        return (new MailMessage)
            ->subject('Verifikasi Email SmartAgri XR')
            ->greeting("Halo, {$this->userName}!")
            ->line('Terima kasih telah mendaftar di SmartAgri XR.')
            ->line('Klik tombol di bawah untuk memverifikasi email kamu.')
            ->action('Verifikasi Email', $verifyUrl)
            ->line('Link ini berlaku selama **24 jam**.')
            ->line('Jika kamu tidak merasa mendaftar, abaikan email ini.')
            ->salutation('Salam, Tim SmartAgri XR 🌾');
    }
}