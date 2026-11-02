<?php

namespace App\Actions;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\VokasimNotification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class NotifyVokasimUser
{
    /** @param array<string, int|string> $parameters */
    public function handle(User $recipient, string $eventKey, string $category, string $title, string $message, string $routeName, array $parameters = []): bool
    {
        if ($recipient->getAttribute('is_active') === false) {
            return false;
        }

        if (! Route::has($routeName) || NotificationCategory::tryFrom($category) === null) {
            throw new InvalidArgumentException('Kategori atau rute notifikasi tidak tersedia.');
        }

        $notification = new VokasimNotification([
            'category' => $category,
            'title' => Str::limit($title, 180),
            'message' => Str::limit($message, 500),
            'route' => $routeName,
            'parameters' => $parameters,
        ]);
        $record = $recipient->notifications()->firstOrCreate(
            ['deduplication_key' => hash('sha256', $eventKey)],
            ['id' => (string) Str::uuid(), 'type' => VokasimNotification::class, 'data' => $notification->toArray($recipient)],
        );

        if ($record->wasRecentlyCreated && config('notifications.email_enabled', false) && $recipient->hasVerifiedEmail()) {
            try {
                $recipient->notify($notification);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $record->wasRecentlyCreated;
    }
}
