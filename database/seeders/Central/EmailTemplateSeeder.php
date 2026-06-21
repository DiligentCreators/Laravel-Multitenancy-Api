<?php

declare(strict_types=1);

namespace Database\Seeders\Central;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Welcome Email',
                'slug' => 'welcome-email',
                'subject' => 'Welcome to {{app_name}}, {{user_name}}!',
                'body' => "Hi {{user_name}},\n\nWelcome to {{app_name}}! We're excited to have you on board.\n\nYour account has been created successfully. You can now log in and start exploring our platform.\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => ['app_name', 'user_name'],
            ],
            [
                'name' => 'Password Reset',
                'slug' => 'password-reset',
                'subject' => 'Reset Your Password',
                'body' => "Hi {{user_name}},\n\nYou have requested to reset your password. Click the link below to reset it:\n\n{{reset_link}}\n\nThis link will expire in 60 minutes.\n\nIf you did not request this, please ignore this email.\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => ['app_name', 'user_name', 'reset_link'],
            ],
            [
                'name' => 'Subscription Created',
                'slug' => 'subscription-created',
                'subject' => 'Subscription Confirmed — {{plan_name}}',
                'body' => "Hi {{user_name}},\n\nYour subscription to the {{plan_name}} plan has been confirmed!\n\n- Plan: {{plan_name}}\n- Amount: {{amount}}\n- Next Billing: {{next_billing_date}}\n\nThank you for subscribing!\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => ['app_name', 'user_name', 'plan_name', 'amount', 'next_billing_date'],
            ],
            [
                'name' => 'Subscription Expired',
                'slug' => 'subscription-expired',
                'subject' => 'Your Subscription Has Expired',
                'body' => "Hi {{user_name}},\n\nYour {{plan_name}} subscription has expired. To continue enjoying our services, please renew your subscription.\n\nRenew now: {{renew_link}}\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => ['app_name', 'user_name', 'plan_name', 'renew_link'],
            ],
            [
                'name' => 'Invoice Created',
                'slug' => 'invoice-created',
                'subject' => 'New Invoice — {{invoice_number}}',
                'body' => "Hi {{user_name}},\n\nA new invoice has been generated for your account.\n\n- Invoice: {{invoice_number}}\n- Amount: {{amount}}\n- Due Date: {{due_date}}\n\nPlease make the payment before the due date.\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => ['app_name', 'user_name', 'invoice_number', 'amount', 'due_date'],
            ],
            [
                'name' => 'Invoice Paid',
                'slug' => 'invoice-paid',
                'subject' => 'Payment Received — {{invoice_number}}',
                'body' => "Hi {{user_name}},\n\nThank you! Your payment for invoice {{invoice_number}} has been received.\n\n- Amount Paid: {{amount}}\n- Transaction ID: {{transaction_id}}\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => ['app_name', 'user_name', 'invoice_number', 'amount', 'transaction_id'],
            ],
            [
                'name' => 'Ticket Created',
                'slug' => 'ticket-created',
                'subject' => 'Support Ticket Created — {{ticket_number}}',
                'body' => "Hi {{user_name}},\n\nYour support ticket has been created successfully.\n\n- Ticket: {{ticket_number}}\n- Subject: {{ticket_subject}}\n\nOur team will get back to you shortly.\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => ['app_name', 'user_name', 'ticket_number', 'ticket_subject'],
            ],
            [
                'name' => 'Ticket Replied',
                'slug' => 'ticket-replied',
                'subject' => 'New Reply on Ticket — {{ticket_number}}',
                'body' => "Hi {{user_name}},\n\nThere is a new reply on your support ticket {{ticket_number}}.\n\n{{reply_content}}\n\nYou can view the full conversation in your dashboard.\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => ['app_name', 'user_name', 'ticket_number', 'reply_content'],
            ],
            [
                'name' => 'Tenant Created',
                'slug' => 'tenant-created',
                'subject' => 'Your Account Has Been Created',
                'body' => "Hi {{user_name}},\n\nYour account for {{tenant_name}} has been created successfully.\n\nYou can now log in and start configuring your workspace.\n\nBest regards,\nThe {{app_name}} Team",
                'variables' => ['app_name', 'user_name', 'tenant_name'],
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::firstOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
