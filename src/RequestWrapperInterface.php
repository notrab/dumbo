<?php

namespace Dumbo;

/**
 * Interface for the RequestWrapper class
 */
interface RequestWrapperInterface
{
    public function param(string $name): ?string;
    public function queries(string $name): array|string;
    public function query(?string $name = null): array|string|null;
    public function body(): array;
    public function method(): string;
    public function headers(?string $name = null): array;
    public function header(string $name): ?string;
    public function path(): string;
    public function routePath(): string;
    public function getUploadedFiles(): array;
}
