<?php

namespace Dumbo\Stream;

/**
 * Passed to $context->stream() and $context->streamSSE() callbacks to send
 * the response body a piece at a time
 */
class StreamWriter
{
    /**
     * @param \Closure(string): void $output Sends a chunk to the client
     */
    public function __construct(private \Closure $output)
    {
    }

    public function write(string $chunk): self
    {
        ($this->output)($chunk);

        return $this;
    }

    public function writeln(string $line): self
    {
        return $this->write($line . "\n");
    }

    /**
     * Send a server-sent event. Arrays and objects are JSON encoded.
     */
    public function writeSSE(
        mixed $data,
        ?string $event = null,
        ?string $id = null,
        ?int $retry = null
    ): self {
        $data = is_string($data) ? $data : (string) json_encode($data);
        $message = "";

        foreach (["event" => $event, "id" => $id, "retry" => $retry] as $field => $value) {
            if ($value !== null) {
                $message .= "$field: " . self::singleLine((string) $value) . "\n";
            }
        }

        foreach (preg_split('/\r\n|\r|\n/', $data) as $line) {
            $message .= "data: $line\n";
        }

        return $this->write($message . "\n");
    }

    public function sleep(float $seconds): self
    {
        usleep((int) ($seconds * 1_000_000));

        return $this;
    }

    /**
     * Whether the client has disconnected. Only reliable after a write.
     */
    public function aborted(): bool
    {
        return connection_aborted() === 1;
    }

    private static function singleLine(string $value): string
    {
        return str_replace(["\r", "\n"], "", $value);
    }
}
