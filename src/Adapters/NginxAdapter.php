<?php

namespace Dumbo\Adapters;

use Dumbo\ServerInterface;

class NginxAdapter implements ServerInterface
{
    public function getMethod(): string
    {
        return $_SERVER["REQUEST_METHOD"];
    }

    public function getUri(): string
    {
        return $_SERVER["REQUEST_URI"];
    }

    public function getHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, "HTTP_") === 0) {
                $headers[
                    str_replace(
                        " ",
                        "-",
                        ucwords(
                            strtolower(str_replace("_", " ", substr($key, 5)))
                        )
                    )
                ] = $value;
            } elseif (in_array($key, ["CONTENT_TYPE", "CONTENT_LENGTH"])) {
                $headers[
                    str_replace(
                        " ",
                        "-",
                        ucwords(strtolower(str_replace("_", " ", $key)))
                    )
                ] = $value;
            }
        }

        return $headers;
    }

    public function getBody()
    {
        return file_get_contents("php://input");
    }

    public function sendResponse(int $statusCode, array $headers, string $body)
    {
        http_response_code($statusCode);

        foreach ($headers as $name => $value) {
            header("$name: $value");
        }

        echo $body;
    }
}
