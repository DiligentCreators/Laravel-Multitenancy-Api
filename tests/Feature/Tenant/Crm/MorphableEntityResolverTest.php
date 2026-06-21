<?php

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
use App\Services\Crm\MorphableEntityResolver;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->resolver = app(MorphableEntityResolver::class);
});

it('resolves lowercase keys to FQCNs', function () {
    expect($this->resolver->resolve('person'))->toBe(Person::class);
    expect($this->resolver->resolve('organization'))->toBe(Organization::class);
    expect($this->resolver->resolve('lead'))->toBe(Lead::class);
    expect($this->resolver->resolve('activity'))->toBe(Activity::class);
    expect($this->resolver->resolve('note'))->toBe(Note::class);
    expect($this->resolver->resolve('comment'))->toBe(Comment::class);
    expect($this->resolver->resolve('task'))->toBe(Task::class);
    expect($this->resolver->resolve('calendar-event'))->toBe(CalendarEvent::class);
    expect($this->resolver->resolve('conversation'))->toBe(Conversation::class);
    expect($this->resolver->resolve('document'))->toBe(Document::class);
    expect($this->resolver->resolve('whatsapp_message'))->toBe(WhatsAppMessage::class);
});

it('resolves FQCNs to themselves', function () {
    expect($this->resolver->resolve(Person::class))->toBe(Person::class);
    expect($this->resolver->resolve(Organization::class))->toBe(Organization::class);
});

it('rejects unknown types', function () {
    $this->resolver->resolve('invalid_type');
})->throws(ValidationException::class);

it('getValidationRule contains all allowed values', function () {
    $rule = $this->resolver->getValidationRule();

    expect($rule)->toHaveCount(3);
    expect($rule[0])->toBe('required');
    expect($rule[1])->toBe('string');
    expect($rule[2])->toMatch('/^in:person,organization,lead,activity,note,comment,task,calendar-event,conversation,document,whatsapp_message/');
});

it('getAllowedClasses returns all FQCNs', function () {
    $classes = $this->resolver->getAllowedClasses();

    expect($classes)->toContain(Person::class);
    expect($classes)->toContain(Organization::class);
    expect($classes)->toContain(Lead::class);
    expect($classes)->toContain(Activity::class);
    expect($classes)->toContain(Note::class);
    expect($classes)->toContain(Comment::class);
    expect($classes)->toContain(Task::class);
    expect($classes)->toContain(CalendarEvent::class);
    expect($classes)->toContain(User::class);
    expect($classes)->toContain(WhatsAppMessage::class);
    expect($classes)->toHaveCount(12);
});

it('getMorphKey returns lowercase key for FQCN', function () {
    expect($this->resolver->getMorphKey(Person::class))->toBe('person');
    expect($this->resolver->getMorphKey(Organization::class))->toBe('organization');
    expect($this->resolver->getMorphKey(Task::class))->toBe('task');
    expect($this->resolver->getMorphKey(CalendarEvent::class))->toBe('calendar-event');
    expect($this->resolver->getMorphKey('Unknown\Class'))->toBeNull();
});
