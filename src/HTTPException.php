<?php

namespace Dumbo;

use Psr\Http\Message\ResponseInterface;

class HTTPException extends \Exception
{
    public function __construct(
        int $statusCode,
        string $message = "",
        private string $errorCode = "",
        private array $errorDetails = [],
        private ?ResponseInterface $customResponse = null
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }

    public function getCustomResponse(): ?ResponseInterface
    {
        return $this->customResponse;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    public function toArray(): array
    {
        return [
            "status" => $this->getCode(),
            "error" => [
                "code" => $this->errorCode,
                "message" => $this->getMessage(),
                "details" => $this->errorDetails,
            ],
        ];
    }
}
