<?php

namespace Dumbo\Stream;

use Psr\Http\Message\StreamInterface;

/**
 * A response body produced by a callback when the response is sent, so it
 * reaches the client as it is written. Reading it any other way, e.g. in a
 * test, runs the callback and buffers the result.
 */
class CallbackStream implements StreamInterface
{
    private bool $ran = false;
    private string $buffer = "";
    private int $position = 0;

    /**
     * @param \Closure(StreamWriter): void $callback
     */
    public function __construct(private \Closure $callback)
    {
    }

    /**
     * Run the callback, passing each chunk to $output as it is written. If
     * the body was already read, the rest of it is passed in one chunk.
     *
     * @param callable(string): void $output
     */
    public function pipe(callable $output): void
    {
        if ($this->ran) {
            $output($this->getContents());

            return;
        }

        $this->ran = true;
        ($this->callback)(new StreamWriter(\Closure::fromCallable($output)));
    }

    /**
     * The whole body, running the callback if it hasn't run yet
     */
    public function __toString(): string
    {
        $this->position = strlen($this->buffered());

        return $this->buffer;
    }

    public function getContents(): string
    {
        $contents = substr($this->buffered(), $this->position);
        $this->position = strlen($this->buffered());

        return $contents;
    }

    public function read(int $length): string
    {
        $chunk = substr($this->buffered(), $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function eof(): bool
    {
        return $this->ran && $this->position >= strlen($this->buffer);
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new \RuntimeException("Cannot write to a callback stream");
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new \RuntimeException("Cannot seek a callback stream");
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function close(): void
    {
        $this->detach();
    }

    public function detach()
    {
        $this->ran = true;
        $this->buffer = "";
        $this->position = 0;

        return null;
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }

    private function buffered(): string
    {
        if (!$this->ran) {
            $this->ran = true;
            ($this->callback)(new StreamWriter(function (string $chunk) {
                $this->buffer .= $chunk;
            }));
        }

        return $this->buffer;
    }
}
