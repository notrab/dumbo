<?php

namespace Dumbo;

interface ServerInterface
{
    public function getMethod(): string;
    public function getUri(): string;
    public function getHeaders(): array;
    public function getBody();
    public function sendResponse(int $statusCode, array $headers, string $body);
}
