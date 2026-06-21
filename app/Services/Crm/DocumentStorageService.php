<?php

namespace App\Services\Crm;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentStorageService
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    private const MAX_FILE_SIZE = 50 * 1024 * 1024;

    public function __construct(
        private readonly FeatureGateService $featureGate,
    ) {}

    public function store(UploadedFile $file, string $tenantId): array
    {
        $tenant = Tenant::findOrFail($tenantId);

        $this->featureGate->assert($tenant, 'documents.upload');

        $this->enforceStorageQuota($tenant, $file->getSize());

        $detectedMime = $file->getMimeType();
        $detectedSize = $file->getSize();

        $this->validateMime($detectedMime);
        $this->validateSize($detectedSize);

        $path = $file->store("{$tenantId}/documents", 'documents');

        $this->featureGate->incrementUsage($tenant, 'documents.storage_mb', $this->bytesToMb($detectedSize));

        return [
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $detectedMime,
            'file_size' => $detectedSize,
            'extension' => $file->getClientOriginalExtension(),
        ];
    }

    public function delete(string $filePath, ?int $fileSize = null): bool
    {
        if ($filePath && Storage::disk('documents')->exists($filePath)) {
            $result = Storage::disk('documents')->delete($filePath);

            if ($result && $fileSize && tenant()) {
                $this->featureGate->decrementUsage(tenant(), 'documents.storage_mb', $this->bytesToMb($fileSize));
            }

            return $result;
        }

        return false;
    }

    public function exists(string $filePath): bool
    {
        return Storage::disk('documents')->exists($filePath);
    }

    public function download(string $filePath, string $fileName)
    {
        if (! $this->exists($filePath)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('documents')->download($filePath, $fileName);
    }

    public function validateMime(?string $mime): void
    {
        if ($mime === null) {
            return;
        }

        abort_if(! in_array($mime, self::ALLOWED_MIMES, true), 422, "File type '{$mime}' is not allowed.");
    }

    public function validateSize(int $size): void
    {
        abort_if($size > self::MAX_FILE_SIZE, 422, 'File size exceeds maximum allowed size of 50MB.');
    }

    public function getAllowedMimes(): array
    {
        return self::ALLOWED_MIMES;
    }

    public function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    private function enforceStorageQuota(Tenant $tenant, int $fileSize): void
    {
        $fileMb = $this->bytesToMb($fileSize);

        $remaining = $this->featureGate->remaining($tenant, 'documents.storage_mb');

        if ($remaining !== null && $fileMb > $remaining) {
            Log::warning('Storage quota exceeded', [
                'tenant_id' => $tenant->id,
                'feature' => 'documents.storage_mb',
                'remaining_mb' => $remaining,
                'required_mb' => $fileMb,
            ]);

            throw ValidationException::withMessages([
                'file' => "Storage quota exceeded. You have {$remaining}MB remaining, but need {$fileMb}MB.",
            ]);
        }
    }

    private function bytesToMb(int $bytes): int
    {
        return (int) ceil($bytes / (1024 * 1024));
    }
}
