<?php

declare(strict_types=1);

namespace Database\Seeders\Central;

use App\Models\Setting;
use App\Models\SettingGroup;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'name' => 'General',
                'slug' => 'general',
                'description' => 'General application settings',
                'sort_order' => 1,
                'settings' => [
                    ['key' => 'app_name', 'label' => 'App Name', 'type' => 'text', 'default_value' => 'FollowKa', 'sort_order' => 1],
                    ['key' => 'app_url', 'label' => 'App URL', 'type' => 'url', 'default_value' => 'https://followka.com', 'sort_order' => 2],
                    ['key' => 'timezone', 'label' => 'Timezone', 'type' => 'select', 'default_value' => 'UTC', 'sort_order' => 3],
                    ['key' => 'date_format', 'label' => 'Date Format', 'type' => 'text', 'default_value' => 'Y-m-d', 'sort_order' => 4],
                    ['key' => 'time_format', 'label' => 'Time Format', 'type' => 'text', 'default_value' => 'H:i:s', 'sort_order' => 5],
                    ['key' => 'default_language', 'label' => 'Default Language', 'type' => 'text', 'default_value' => 'en', 'sort_order' => 6],
                ],
            ],
            [
                'name' => 'Branding',
                'slug' => 'branding',
                'description' => 'Branding and appearance settings',
                'sort_order' => 2,
                'settings' => [
                    ['key' => 'logo', 'label' => 'Logo', 'type' => 'file', 'sort_order' => 1],
                    ['key' => 'favicon', 'label' => 'Favicon', 'type' => 'file', 'sort_order' => 2],
                    ['key' => 'primary_color', 'label' => 'Primary Color', 'type' => 'text', 'default_value' => '#2563eb', 'sort_order' => 3],
                    ['key' => 'secondary_color', 'label' => 'Secondary Color', 'type' => 'text', 'default_value' => '#64748b', 'sort_order' => 4],
                    ['key' => 'footer_text', 'label' => 'Footer Text', 'type' => 'text', 'default_value' => '© FollowKa. All rights reserved.', 'sort_order' => 5],
                ],
            ],
            [
                'name' => 'Email',
                'slug' => 'email',
                'description' => 'Email configuration settings',
                'sort_order' => 3,
                'settings' => [
                    ['key' => 'mailer', 'label' => 'Mailer', 'type' => 'select', 'default_value' => 'smtp', 'sort_order' => 1],
                    ['key' => 'host', 'label' => 'Host', 'type' => 'text', 'sort_order' => 2],
                    ['key' => 'port', 'label' => 'Port', 'type' => 'number', 'default_value' => '587', 'sort_order' => 3],
                    ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'sort_order' => 4],
                    ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'is_encrypted' => true, 'sort_order' => 5],
                    ['key' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'default_value' => 'tls', 'sort_order' => 6],
                    ['key' => 'from_name', 'label' => 'From Name', 'type' => 'text', 'default_value' => 'FollowKa', 'sort_order' => 7],
                    ['key' => 'from_email', 'label' => 'From Email', 'type' => 'email', 'default_value' => 'noreply@followka.com', 'sort_order' => 8],
                ],
            ],
            [
                'name' => 'SMS',
                'slug' => 'sms',
                'description' => 'SMS gateway configuration',
                'sort_order' => 4,
                'settings' => [
                    ['key' => 'provider', 'label' => 'Provider', 'type' => 'select', 'default_value' => 'twilio', 'sort_order' => 1],
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'is_encrypted' => true, 'sort_order' => 2],
                    ['key' => 'sender_id', 'label' => 'Sender ID', 'type' => 'text', 'default_value' => 'FollowKa', 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'Security',
                'slug' => 'security',
                'description' => 'Security and authentication settings',
                'sort_order' => 5,
                'settings' => [
                    ['key' => '2fa_enabled', 'label' => '2FA Enabled', 'type' => 'boolean', 'default_value' => 'false', 'sort_order' => 1],
                    ['key' => 'password_expiry', 'label' => 'Password Expiry (days)', 'type' => 'number', 'default_value' => '90', 'sort_order' => 2],
                    ['key' => 'session_timeout', 'label' => 'Session Timeout (minutes)', 'type' => 'number', 'default_value' => '120', 'sort_order' => 3],
                    ['key' => 'max_login_attempts', 'label' => 'Max Login Attempts', 'type' => 'number', 'default_value' => '5', 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'Billing',
                'slug' => 'billing',
                'description' => 'Billing and subscription settings',
                'sort_order' => 6,
                'settings' => [
                    ['key' => 'trial_days', 'label' => 'Trial Days', 'type' => 'number', 'default_value' => '14', 'sort_order' => 1],
                    ['key' => 'grace_period', 'label' => 'Grace Period (days)', 'type' => 'number', 'default_value' => '7', 'sort_order' => 2],
                    ['key' => 'tax_percentage', 'label' => 'Tax Percentage', 'type' => 'number', 'default_value' => '0', 'sort_order' => 3],
                    ['key' => 'currency', 'label' => 'Currency', 'type' => 'text', 'default_value' => 'USD', 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'Storage',
                'slug' => 'storage',
                'description' => 'File storage configuration',
                'sort_order' => 7,
                'settings' => [
                    ['key' => 'provider', 'label' => 'Provider', 'type' => 'select', 'default_value' => 'local', 'sort_order' => 1],
                    ['key' => 'bucket', 'label' => 'Bucket', 'type' => 'text', 'sort_order' => 2],
                    ['key' => 'region', 'label' => 'Region', 'type' => 'text', 'sort_order' => 3],
                    ['key' => 'access_key', 'label' => 'Access Key', 'type' => 'password', 'is_encrypted' => true, 'sort_order' => 4],
                    ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'is_encrypted' => true, 'sort_order' => 5],
                ],
            ],
        ];

        foreach ($groups as $groupData) {
            $settings = $groupData['settings'];
            unset($groupData['settings']);

            $group = SettingGroup::firstOrCreate(
                ['slug' => $groupData['slug']],
                $groupData
            );

            foreach ($settings as $settingData) {
                $settingData['group_id'] = $group->id;
                Setting::firstOrCreate(
                    ['key' => $settingData['key']],
                    $settingData
                );
            }
        }
    }
}
