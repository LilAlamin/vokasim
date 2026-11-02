<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** @phpstan-type NoticeDetails array{category: string, title: string, message: string, route: string, parameters: array<string, int|string>} */
class VokasimNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param NoticeDetails $details */
    public function __construct(public readonly array $details)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->details['title'])
            ->greeting('Pembaruan VokaSim')
            ->line($this->details['message'])
            ->action('Lihat pembaruan', route($this->details['route'], $this->details['parameters']))
            ->line('Masuk menggunakan akun Anda untuk melihat detail yang bersifat privat.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return NoticeDetails
     */
    public function toArray(object $notifiable): array
    {
        return $this->details;
    }
}
