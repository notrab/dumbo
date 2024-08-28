<?php

namespace Dumbo;

use Psr\Http\Message\ResponseInterface;

class HTTPException extends \Exception
{
    private int $statusCode;
    private ?ResponseInterface $customResponse;
    private string $errorCode;
    private array $errorDetails;

    public function __construct(
        int $statusCode,
        string $message = "",
        string $errorCode = "",
        array $errorDetails = [],
        ResponseInterface $customResponse = null
    ) {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->customResponse = $customResponse;
        $this->errorCode = $errorCode;
        $this->errorDetails = $errorDetails;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
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
            "status" => $this->statusCode,
            "error" => [
                "code" => $this->errorCode,
                "message" => $this->getMessage(),
                "details" => $this->errorDetails,
            ],
        ];
    }
}
