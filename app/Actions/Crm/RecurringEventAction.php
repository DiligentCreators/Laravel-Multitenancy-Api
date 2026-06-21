<?php

namespace App\Actions\Crm;

use App\Models\Crm\CalendarEvent;
use App\Models\Crm\RecurringEventPattern;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringEventAction
{
    private const MAX_OCCURRENCES = 365;

    public function generate(CalendarEvent $event, array $recurringData): RecurringEventPattern
    {
        return DB::transaction(function () use ($event, $recurringData) {
            $pattern = RecurringEventPattern::create([
                'tenant_id' => $event->tenant_id,
                'created_by' => $event->created_by,
                'updated_by' => $event->updated_by,
                'frequency' => $recurringData['frequency'],
                'interval' => $recurringData['interval'] ?? 1,
                'ends_at' => $recurringData['ends_at'] ?? null,
                'occurrences_limit' => $recurringData['occurrences_limit'] ?? null,
            ]);

            $event->update(['recurring_event_pattern_id' => $pattern->id]);

            $this->generateOccurrences($event, $pattern);

            return $pattern;
        });
    }

    public function generateOccurrences(CalendarEvent $event, RecurringEventPattern $pattern): void
    {
        $occurrences = $this->calculateOccurrences($event, $pattern);

        foreach ($occurrences as $occurrence) {
            CalendarEvent::create([
                'tenant_id' => $event->tenant_id,
                'owner_id' => $event->owner_id,
                'team_id' => $event->team_id,
                'created_by' => $event->created_by,
                'updated_by' => $event->updated_by,
                'title' => $event->title,
                'description' => $event->description,
                'starts_at' => $occurrence['starts_at'],
                'ends_at' => $occurrence['ends_at'],
                'all_day' => $event->all_day ?? false,
                'status' => $event->status ?? 'scheduled',
                'location' => $event->location,
                'color' => $event->color,
                'eventable_type' => $event->eventable_type,
                'eventable_id' => $event->eventable_id,
                'recurring_event_pattern_id' => $pattern->id,
            ]);
        }
    }

    private function calculateOccurrences(CalendarEvent $event, RecurringEventPattern $pattern): array
    {
        $start = $event->starts_at->copy()->add($pattern->interval, $this->frequencyUnit($pattern->frequency->value));
        $endAt = $pattern->ends_at ? Carbon::parse($pattern->ends_at) : null;
        $limit = $pattern->occurrences_limit ?? self::MAX_OCCURRENCES;

        $occurrences = [];
        $count = 0;

        $duration = $event->ends_at ? $event->starts_at->diffInSeconds($event->ends_at) : 0;

        while ($count < $limit) {
            if ($endAt && $start->greaterThan($endAt)) {
                break;
            }

            $occurrences[] = [
                'starts_at' => $start->copy(),
                'ends_at' => $duration > 0 ? $start->copy()->addSeconds($duration) : null,
            ];

            $count++;
            $start->add($pattern->interval, $this->frequencyUnit($pattern->frequency->value));
        }

        return $occurrences;
    }

    private function frequencyUnit(string $frequency): string
    {
        return match ($frequency) {
            'daily' => 'day',
            'weekly' => 'week',
            'monthly' => 'month',
            'yearly' => 'year',
            default => 'day',
        };
    }
}
