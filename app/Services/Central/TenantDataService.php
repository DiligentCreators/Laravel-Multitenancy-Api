<?php

namespace App\Services\Central;

use App\Models\AdminAuditLog;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Models\TenantExportRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantDataService
{
    /**
     * Export tenant data to a file.
     *
     * @return array{record: TenantExportRecord, file_path: string, size: int}
     */
    public function export(Tenant $tenant, string $type = 'full', string $format = 'json', ?CentralUser $user = null): array
    {
        return DB::transaction(function () use ($tenant, $type, $format, $user) {
            $record = TenantExportRecord::create([
                'tenant_id' => $tenant->id,
                'central_user_id' => $user?->id,
                'type' => $type,
                'format' => $format,
                'status' => 'processing',
            ]);

            try {
                $data = $this->collectData($tenant, $type);
                $filename = "exports/tenant_{$tenant->id}_{$type}_{$record->id}.{$format}";

                if ($format === 'json') {
                    Storage::disk('local')->put($filename, json_encode($data, JSON_PRETTY_PRINT));
                } elseif ($format === 'csv') {
                    Storage::disk('local')->put($filename, $this->toCsv($data));
                }

                $size = Storage::disk('local')->size($filename);

                $record->update([
                    'file_path' => $filename,
                    'file_size' => $size,
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                if ($user) {
                    AdminAuditLog::create([
                        'central_user_id' => $user->id,
                        'action' => 'data_export',
                        'context' => [
                            'tenant_id' => $tenant->id,
                            'export_type' => $type,
                            'format' => $format,
                            'record_id' => $record->id,
                        ],
                    ]);
                }

                return [
                    'record' => $record,
                    'file_path' => $filename,
                    'size' => $size,
                ];
            } catch (\Throwable $e) {
                $record->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function collectData(Tenant $tenant, string $type): array
    {
        $data = [
            'tenant' => $tenant->toArray(),
        ];

        if ($type === 'full' || $type === 'settings') {
            $data['settings'] = $tenant->settings?->toArray() ?? [];
        }

        if ($type === 'full' || $type === 'users') {
            $data['users'] = $tenant->users()?->get()?->toArray() ?? [];
            $data['subscriptions'] = $tenant->subscriptions()?->get()?->toArray() ?? [];
        }

        if ($type === 'full' || $type === 'activity') {
            $data['invoices'] = $tenant->invoices()?->get()?->toArray() ?? [];
            $data['payments'] = $tenant->payments()?->get()?->toArray() ?? [];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function toCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        foreach ($data as $section => $items) {
            if (is_array($items) && isset($items[0]) && is_array($items[0])) {
                fputcsv($output, array_keys($items[0]));
                foreach ($items as $row) {
                    fputcsv($output, $row);
                }
            }
        }

        rewind($output);

        return stream_get_contents($output);
    }

    public function cleanup(int $daysOlderThan = 30): int
    {
        $count = TenantExportRecord::where('status', 'completed')
            ->where('created_at', '<', now()->subDays($daysOlderThan))
            ->count();

        TenantExportRecord::where('status', 'completed')
            ->where('created_at', '<', now()->subDays($daysOlderThan))
            ->chunk(100, function ($records) {
                foreach ($records as $record) {
                    if ($record->file_path && Storage::disk('local')->exists($record->file_path)) {
                        Storage::disk('local')->delete($record->file_path);
                    }
                    $record->delete();
                }
            });

        return $count;
    }
}
