<?php

use App\Enums\Central\SubscriptionStatusEnum;
use App\Enums\ConversationChannelEnum;
use App\Enums\WhatsAppMessageStatusEnum;
use App\Models\Crm\Conversation;
use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\Person;
use App\Models\Crm\WhatsAppAccount;
use App\Models\Crm\WhatsAppMessage;
use App\Models\Crm\WhatsAppPhoneNumber;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

function webhookTestTenant(): Tenant
{
    $domain = 'wh-'.uniqid().'.localhost';
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

function webhookTestUser(Tenant $tenant): User
{
    return User::factory()->create(['tenant_id' => $tenant->id]);
}

uses(RefreshDatabase::class);

beforeEach(function () {
    FeatureDefinition::create(['key' => 'whatsapp.enabled', 'name' => 'WhatsApp Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false]);
    $this->tenant = webhookTestTenant();
    $this->user = webhookTestUser($this->tenant);
    tenancy()->initialize($this->tenant);
    $plan = Plan::factory()->create(['is_active' => true]);
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
        'billing_cycle' => 'monthly',
        'status' => SubscriptionStatusEnum::ACTIVE,
    ]);

    $this->account = WhatsAppAccount::factory()->create([
        'webhook_verify_token' => 'my-verify-token',
    ]);
    $this->phoneNumber = WhatsAppPhoneNumber::factory()->create([
        'whatsapp_account_id' => $this->account->id,
        'display_phone_number' => '+12025550199',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('verifies webhook with valid challenge', function () {
    $response = $this->getJson("/api/tenant/v1/crm/webhook/whatsapp/{$this->account->id}?hub_mode=subscribe&hub_verify_token=my-verify-token&hub_challenge=challenge123");

    $response->assertOk();
    expect($response->content())->toBe('challenge123');
});

it('rejects webhook with invalid verify token', function () {
    $response = $this->getJson("/api/tenant/v1/crm/webhook/whatsapp/{$this->account->id}?hub_mode=subscribe&hub_verify_token=wrong-token&hub_challenge=challenge123");

    $response->assertStatus(403);
});

it('rejects webhook with missing token', function () {
    $response = $this->getJson("/api/tenant/v1/crm/webhook/whatsapp/{$this->account->id}?hub_mode=subscribe&hub_challenge=challenge123");

    $response->assertStatus(403);
});

it('processes inbound text message webhook', function () {
    $payload = [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'metadata' => [
                                'display_phone_number' => '+12025550199',
                            ],
                            'messages' => [
                                [
                                    'from' => '+15551234567',
                                    'id' => 'whatsapp-msg-id-1',
                                    'type' => 'text',
                                    'text' => [
                                        'body' => 'Hello from customer',
                                    ],
                                    'timestamp' => now()->timestamp,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->postJson("/api/tenant/v1/crm/webhook/whatsapp/{$this->account->id}", $payload);

    $response->assertOk();

    $this->assertDatabaseHas('crm_whatsapp_messages', [
        'provider_message_id' => 'whatsapp-msg-id-1',
        'content' => 'Hello from customer',
        'from_number' => '+15551234567',
        'to_number' => '+12025550199',
    ]);

    $this->assertDatabaseHas('crm_whatsapp_webhook_logs', [
        'event_type' => 'webhook_received',
    ]);
});

it('creates person for unknown sender', function () {
    $payload = [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'metadata' => [
                                'display_phone_number' => '+12025550199',
                            ],
                            'messages' => [
                                [
                                    'from' => '+19998887777',
                                    'id' => 'msg-new-person',
                                    'type' => 'text',
                                    'text' => [
                                        'body' => 'Hello, I am new',
                                    ],
                                    'timestamp' => now()->timestamp,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->postJson("/api/tenant/v1/crm/webhook/whatsapp/{$this->account->id}", $payload);

    $this->assertDatabaseHas('crm_people', [
        'phone' => '+19998887777',
        'first_name' => 'Unknown',
    ]);
});

it('creates conversation for existing person', function () {
    $person = Person::create(['first_name' => 'Existing', 'last_name' => 'Person', 'phone' => '+15551234567']);

    $payload = [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'metadata' => [
                                'display_phone_number' => '+12025550199',
                            ],
                            'messages' => [
                                [
                                    'from' => '+15551234567',
                                    'id' => 'msg-conv-existing',
                                    'type' => 'text',
                                    'text' => [
                                        'body' => 'Message for existing person',
                                    ],
                                    'timestamp' => now()->timestamp,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->postJson("/api/tenant/v1/crm/webhook/whatsapp/{$this->account->id}", $payload);

    $this->assertDatabaseHas('crm_conversations', [
        'channel' => ConversationChannelEnum::WHATSAPP->value,
    ]);

    $conversation = Conversation::where('channel', ConversationChannelEnum::WHATSAPP)->first();
    expect($conversation)->not->toBeNull();
    $this->assertDatabaseHas('crm_conversation_participants', [
        'conversation_id' => $conversation->id,
        'participant_type' => Person::class,
        'participant_id' => $person->id,
    ]);
});

it('reuses existing open conversation', function () {
    $person = Person::create(['first_name' => 'Reuse', 'last_name' => 'Person', 'phone' => '+15551234567']);

    $existingConv = Conversation::create([
        'uuid' => Str::uuid(),
        'subject' => 'Existing WhatsApp conversation',
        'channel' => ConversationChannelEnum::WHATSAPP,
        'status' => 'open',
    ]);

    $existingConv->participants()->create([
        'participant_type' => Person::class,
        'participant_id' => $person->id,
        'is_primary' => true,
    ]);

    $payload = [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'metadata' => [
                                'display_phone_number' => '+12025550199',
                            ],
                            'messages' => [
                                [
                                    'from' => '+15551234567',
                                    'id' => 'msg-reuse-conv',
                                    'type' => 'text',
                                    'text' => [
                                        'body' => 'Reusing existing conv',
                                    ],
                                    'timestamp' => now()->timestamp,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->postJson("/api/tenant/v1/crm/webhook/whatsapp/{$this->account->id}", $payload);

    $this->assertDatabaseHas('crm_whatsapp_messages', [
        'provider_message_id' => 'msg-reuse-conv',
        'conversation_id' => $existingConv->id,
    ]);

    $conversationCount = Conversation::where('channel', ConversationChannelEnum::WHATSAPP)->count();
    expect($conversationCount)->toBe(1);
});

it('processes message status updates', function () {
    $message = WhatsAppMessage::factory()->create([
        'whatsapp_phone_number_id' => $this->phoneNumber->id,
        'provider_message_id' => 'status-msg-1',
        'status' => WhatsAppMessageStatusEnum::SENT,
    ]);

    $payload = [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'statuses' => [
                                [
                                    'id' => 'status-msg-1',
                                    'status' => 'delivered',
                                    'timestamp' => now()->timestamp,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->postJson("/api/tenant/v1/crm/webhook/whatsapp/{$this->account->id}", $payload);

    $message->refresh();
    expect($message->status->value)->toBe('delivered');
});

it('records timeline events on message received webhook', function () {
    $payload = [
        'entry' => [
            [
                'changes' => [
                    [
                        'value' => [
                            'metadata' => [
                                'display_phone_number' => '+12025550199',
                            ],
                            'messages' => [
                                [
                                    'from' => '+15551234567',
                                    'id' => 'timeline-msg',
                                    'type' => 'text',
                                    'text' => [
                                        'body' => 'Timeline test message',
                                    ],
                                    'timestamp' => now()->timestamp,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->postJson("/api/tenant/v1/crm/webhook/whatsapp/{$this->account->id}", $payload);

    $this->assertDatabaseHas('crm_timeline_entries', [
        'event_type' => 'whatsapp.message_received',
    ]);
});

it('prevents cross-tenant webhook access', function () {
    $otherTenant = webhookTestTenant();
    $otherAccount = null;
    tenancy()->initialize($otherTenant);
    $otherAccount = WhatsAppAccount::factory()->create(['webhook_verify_token' => 'other-token']);
    tenancy()->end();
    tenancy()->initialize($this->tenant);

    $response = $this->postJson("/api/tenant/v1/crm/webhook/whatsapp/{$otherAccount->id}", [
        'entry' => [],
    ]);

    $response->assertNotFound();
});
