<?php

namespace Database\Seeders\Tenant;

use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\Source;
use App\Models\Crm\Status;
use App\Models\Crm\StatusType;
use App\Models\Crm\Tag;
use Illuminate\Database\Seeder;

class CrmDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFeatureDefinitions();
        $this->seedStatusTypes();
        $this->seedDefaultSources();
        $this->seedDefaultTags();
    }

    private function seedFeatureDefinitions(): void
    {
        $features = [
            ['key' => 'people.create', 'name' => 'Create People', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'people.max', 'name' => 'Max People', 'type' => 'integer', 'default_value' => 5000, 'is_usage_limit' => true],
            ['key' => 'organizations.create', 'name' => 'Create Organizations', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'leads.create', 'name' => 'Create Leads', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'leads.max', 'name' => 'Max Leads', 'type' => 'integer', 'default_value' => 1000, 'is_usage_limit' => true],
            ['key' => 'documents.upload', 'name' => 'Upload Documents', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'documents.storage_mb', 'name' => 'Document Storage (MB)', 'type' => 'integer', 'default_value' => 100, 'is_usage_limit' => true],
            ['key' => 'pipelines.manage', 'name' => 'Manage Pipelines', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'tasks.enabled', 'name' => 'Tasks Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'reports.enabled', 'name' => 'Reports Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'import.csv', 'name' => 'CSV Import', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'export.csv', 'name' => 'CSV Export', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'api.access', 'name' => 'API Access', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'teams.max', 'name' => 'Max Teams', 'type' => 'integer', 'default_value' => 5, 'is_usage_limit' => true],
            ['key' => 'custom-fields.max', 'name' => 'Max Custom Fields', 'type' => 'integer', 'default_value' => 20, 'is_usage_limit' => true],
            ['key' => 'calendar.enabled', 'name' => 'Calendar Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'communications.enabled', 'name' => 'Communications Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'message_templates.enabled', 'name' => 'Message Templates Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'whatsapp.enabled', 'name' => 'WhatsApp Enabled', 'type' => 'boolean', 'default_value' => true, 'is_usage_limit' => false],
            ['key' => 'overage.allowed', 'name' => 'Overage Allowed', 'type' => 'boolean', 'default_value' => false, 'is_usage_limit' => false],
        ];

        foreach ($features as $feature) {
            FeatureDefinition::firstOrCreate(
                ['key' => $feature['key']],
                $feature
            );
        }
    }

    private function seedStatusTypes(): void
    {
        $types = [
            ['entity_type' => 'person', 'name' => 'Person Statuses', 'key' => 'person_statuses'],
            ['entity_type' => 'organization', 'name' => 'Organization Statuses', 'key' => 'organization_statuses'],
            ['entity_type' => 'lead', 'name' => 'Lead Statuses', 'key' => 'lead_statuses'],
            ['entity_type' => 'task', 'name' => 'Task Statuses', 'key' => 'task_statuses'],
        ];

        foreach ($types as $typeData) {
            $type = StatusType::firstOrCreate(
                ['key' => $typeData['key']],
                $typeData
            );

            $defaultStatuses = match ($type->key) {
                'person_statuses' => [
                    ['name' => 'Active', 'key' => 'active', 'color' => '#22c55e', 'order' => 1, 'is_default' => true],
                    ['name' => 'Inactive', 'key' => 'inactive', 'color' => '#6b7280', 'order' => 2],
                    ['name' => 'Lead', 'key' => 'lead', 'color' => '#3b82f6', 'order' => 3],
                    ['name' => 'Customer', 'key' => 'customer', 'color' => '#10b981', 'order' => 4],
                    ['name' => 'Vendor', 'key' => 'vendor', 'color' => '#8b5cf6', 'order' => 5],
                    ['name' => 'Partner', 'key' => 'partner', 'color' => '#f59e0b', 'order' => 6],
                ],
                'organization_statuses' => [
                    ['name' => 'Active', 'key' => 'active', 'color' => '#22c55e', 'order' => 1, 'is_default' => true],
                    ['name' => 'Inactive', 'key' => 'inactive', 'color' => '#6b7280', 'order' => 2],
                    ['name' => 'Prospect', 'key' => 'prospect', 'color' => '#3b82f6', 'order' => 3],
                ],
                'lead_statuses' => [
                    ['name' => 'New', 'key' => 'new', 'color' => '#6366f1', 'order' => 1, 'is_default' => true],
                    ['name' => 'Qualified', 'key' => 'qualified', 'color' => '#3b82f6', 'order' => 2],
                    ['name' => 'Proposal', 'key' => 'proposal', 'color' => '#f59e0b', 'order' => 3],
                    ['name' => 'Negotiation', 'key' => 'negotiation', 'color' => '#f97316', 'order' => 4],
                    ['name' => 'Won', 'key' => 'won', 'color' => '#22c55e', 'order' => 5],
                    ['name' => 'Lost', 'key' => 'lost', 'color' => '#ef4444', 'order' => 6],
                ],
                'task_statuses' => [
                    ['name' => 'Open', 'key' => 'open', 'color' => '#6366f1', 'order' => 1, 'is_default' => true],
                    ['name' => 'In Progress', 'key' => 'in_progress', 'color' => '#3b82f6', 'order' => 2],
                    ['name' => 'Completed', 'key' => 'completed', 'color' => '#22c55e', 'order' => 3],
                    ['name' => 'Cancelled', 'key' => 'cancelled', 'color' => '#ef4444', 'order' => 4],
                ],
                default => [],
            };

            foreach ($defaultStatuses as $statusData) {
                Status::firstOrCreate(
                    ['type_id' => $type->id, 'key' => $statusData['key']],
                    array_merge($statusData, ['type_id' => $type->id])
                );
            }
        }
    }

    private function seedDefaultSources(): void
    {
        $sources = [
            ['name' => 'Website', 'category' => 'website'],
            ['name' => 'Facebook', 'category' => 'social'],
            ['name' => 'LinkedIn', 'category' => 'social'],
            ['name' => 'Instagram', 'category' => 'social'],
            ['name' => 'Google Ads', 'category' => 'ads'],
            ['name' => 'Facebook Ads', 'category' => 'ads'],
            ['name' => 'Referral', 'category' => 'referral'],
            ['name' => 'Partner', 'category' => 'referral'],
            ['name' => 'Manual', 'category' => 'manual'],
            ['name' => 'Import', 'category' => 'import'],
            ['name' => 'API', 'category' => 'api'],
        ];

        foreach ($sources as $source) {
            Source::firstOrCreate(
                ['name' => $source['name']],
                $source
            );
        }
    }

    private function seedDefaultTags(): void
    {
        $tags = [
            ['name' => 'Hot Lead', 'color' => '#ef4444'],
            ['name' => 'VIP', 'color' => '#f59e0b'],
            ['name' => 'Investor', 'color' => '#8b5cf6'],
            ['name' => 'New', 'color' => '#3b82f6'],
            ['name' => 'Follow Up', 'color' => '#ec4899'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(
                ['name' => $tag['name']],
                $tag
            );
        }
    }
}
