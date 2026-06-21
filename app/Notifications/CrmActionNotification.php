<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class CrmActionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public Model $entity,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'entity_type' => $this->entity->getMorphClass(),
            'entity_id' => $this->entity->getKey(),
        ];
    }
}
