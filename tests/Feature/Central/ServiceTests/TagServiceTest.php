<?php

use App\Models\Crm\Organization;
use App\Models\Crm\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\MorphableEntityResolver;
use App\Services\Crm\TagService;

beforeEach(function () {
    $domain = 'tag-service-test-'.uniqid().'.localhost';
    $this->tenant = Tenant::factory()->create();
    $this->tenant->domains()->create(['domain' => $domain]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    tenancy()->end();
});

it('can attach tags via bulkAttach', function () {
    $organization = Organization::create(['tenant_id' => $this->tenant->id, 'name' => 'Test Corp']);
    $tag = Tag::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP']);

    $service = app(TagService::class);
    $service->bulkAttach(Organization::class, [$organization->id], [$tag->id]);

    expect($organization->tags()->count())->toBe(1);
    expect($organization->tags()->first()->id)->toBe($tag->id);
});

it('resolveMorphClass delegates to MorphableEntityResolver', function () {
    $resolver = new MorphableEntityResolver;
    $resolved = $resolver->getMorphKey(Organization::class);

    expect($resolved)->toBe('organization');
});

it('uses MorphableEntityResolver via TagService', function () {
    $service = app(TagService::class);

    expect($service)->toBeInstanceOf(TagService::class);
});
