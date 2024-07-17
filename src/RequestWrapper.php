<?php

namespace Dumbo;

/**
 * Interface for wrapping request-related methods
 */
interface RequestWrapper
{
    public function param(string $name): ?string;
    public function queries(string $name): array;
    public function query(?string $name = null): array|string|null;
    public function body(): array;
    public function method(): string;
    public function headers(?string $name = null): array;
    public function header(string $name): ?string;
    public function path(): string;
    public function routePath(): string;
}
