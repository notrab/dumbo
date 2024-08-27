<?php

namespace Dumbo;

use Psr\Http\Message\ResponseInterface;

class HTTPException extends \Exception
{
    private $statusCode;
    private $customResponse;

    public function __construct(
        int $statusCode,
        string $message = "",
        ResponseInterface $customResponse = null
    ) {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->customResponse = $customResponse;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getCustomResponse(): ?ResponseInterface
    {
        return $this->customResponse;
    }
}
