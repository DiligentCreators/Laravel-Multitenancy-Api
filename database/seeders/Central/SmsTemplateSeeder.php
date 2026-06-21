<?php

declare(strict_types=1);

namespace Database\Seeders\Central;

use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'OTP Verification',
                'slug' => 'otp-verification',
                'message' => 'Your OTP for {{app_name}} is {{otp_code}}. It expires in {{expiry_minutes}} minutes.',
                'variables' => ['app_name', 'otp_code', 'expiry_minutes'],
            ],
            [
                'name' => 'Invoice Reminder',
                'slug' => 'invoice-reminder',
                'message' => 'Reminder: Invoice {{invoice_number}} for {{amount}} is due on {{due_date}}. Please make the payment to avoid service interruption.',
                'variables' => ['invoice_number', 'amount', 'due_date'],
            ],
            [
                'name' => 'Payment Received',
                'slug' => 'payment-received',
                'message' => 'Payment of {{amount}} for invoice {{invoice_number}} has been received. Thank you!',
                'variables' => ['amount', 'invoice_number'],
            ],
            [
                'name' => 'Subscription Expiry',
                'slug' => 'subscription-expiry',
                'message' => 'Your {{plan_name}} subscription will expire on {{expiry_date}}. Renew now to continue enjoying our services.',
                'variables' => ['plan_name', 'expiry_date'],
            ],
            [
                'name' => 'Ticket Update',
                'slug' => 'ticket-update',
                'message' => 'Your support ticket {{ticket_number}} has a new update. Check your dashboard for details.',
                'variables' => ['ticket_number'],
            ],
        ];

        foreach ($templates as $template) {
            SmsTemplate::firstOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
