<?php

declare(strict_types=1);

namespace Database\Seeders\Central;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'New Tenant Registered',
                'slug' => 'new-tenant-registered',
                'channel' => 'in_app',
                'title' => 'New Tenant Registered',
                'message' => 'Tenant {{tenant_name}} has registered and is ready for review.',
                'variables' => ['tenant_name'],
            ],
            [
                'name' => 'Subscription Expiring',
                'slug' => 'subscription-expiring',
                'channel' => 'email',
                'title' => 'Subscription Expiring Soon',
                'message' => 'Your {{plan_name}} subscription will expire in {{days_remaining}} days.',
                'variables' => ['plan_name', 'days_remaining'],
            ],
            [
                'name' => 'Payment Failed',
                'slug' => 'payment-failed',
                'channel' => 'email',
                'title' => 'Payment Failed',
                'message' => 'Your payment of {{amount}} for invoice {{invoice_number}} has failed. Please update your payment method.',
                'variables' => ['amount', 'invoice_number'],
            ],
            [
                'name' => 'Overage Alert',
                'slug' => 'overage-alert',
                'channel' => 'in_app',
                'title' => 'Usage Limit Reached',
                'message' => 'You have reached {{usage_percent}}% of your {{feature}} limit.',
                'variables' => ['usage_percent', 'feature'],
            ],
            [
                'name' => 'System Announcement',
                'slug' => 'system-announcement',
                'channel' => 'push',
                'title' => 'System Announcement',
                'message' => '{{announcement_message}}',
                'variables' => ['announcement_message'],
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::firstOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
