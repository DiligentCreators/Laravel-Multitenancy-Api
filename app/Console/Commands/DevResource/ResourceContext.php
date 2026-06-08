<?php

declare(strict_types=1);

namespace App\Console\Commands\DevResource;

final readonly class ResourceContext
{
    public function __construct(
        public string $name,
        public string $path,
        public string $context,
        public string $version,
        public array $generators,
        public bool $force = false,
    ) {}
}
