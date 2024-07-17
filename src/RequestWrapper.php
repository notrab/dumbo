<?php

namespace Dumbo;

/**
 * Interface for wrapping request-related methods
 */
interface RequestWrapper
{
    public function param(string $name): ?string;
    public function query(?string $name = null): array|string|null;
    public function body(): array;
    public function method(): string;
    public function headers(?string $name = null): array;
}
