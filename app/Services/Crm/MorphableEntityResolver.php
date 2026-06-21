<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\CalendarEvent;
use App\Models\Crm\Comment;
use App\Models\Crm\Conversation;
use App\Models\Crm\Document;
use App\Models\Crm\Lead;
use App\Models\Crm\Note;
use App\Models\Crm\Organization;
use App\Models\Crm\Person;
use App\Models\Crm\Task;
use App\Models\Crm\WhatsAppMessage;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MorphableEntityResolver
{
    public const ALLOWED_TYPES = [
        'person' => Person::class,
        'organization' => Organization::class,
        'lead' => Lead::class,
        'activity' => Activity::class,
        'note' => Note::class,
        'comment' => Comment::class,
        'task' => Task::class,
        'calendar-event' => CalendarEvent::class,
        'conversation' => Conversation::class,
        'document' => Document::class,
        'whatsapp_message' => WhatsAppMessage::class,
        'user' => User::class,
    ];

    public function resolve(string $type): string
    {
        $key = strtolower($type);

        if (isset(self::ALLOWED_TYPES[$key])) {
            return self::ALLOWED_TYPES[$key];
        }

        $classMap = array_flip(self::ALLOWED_TYPES);

        if (isset($classMap[$type])) {
            return $type;
        }

        throw ValidationException::withMessages([
            'type' => ["The entity type '{$type}' is not allowed."],
        ]);
    }

    public function resolveOrFail(string $type): string
    {
        return $this->resolve($type);
    }

    public function getValidationRule(): array
    {
        $allowedValues = array_merge(
            array_keys(self::ALLOWED_TYPES),
            array_values(self::ALLOWED_TYPES)
        );

        return ['required', 'string', 'in:'.implode(',', $allowedValues)];
    }

    public function getAllowedClasses(): array
    {
        return array_values(self::ALLOWED_TYPES);
    }

    public function getMorphKey(string $fqcn): ?string
    {
        $map = array_flip(self::ALLOWED_TYPES);

        return $map[$fqcn] ?? null;
    }
}
