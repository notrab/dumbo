<?php

namespace Dumbo\Stream;

use Psr\Http\Message\StreamInterface;

/**
 * A response body produced by a callback as it is read. The callback runs in
 * a Fiber that pauses at each write, so each read() returns the next chunk
 * and nothing is buffered.
 */
class CallbackStream implements StreamInterface
{
    private ?\Fiber $fiber = null;
    private string $pending = "";
    private bool $done = false;
    private int $position = 0;

    /**
     * @param \Closure(StreamWriter): void $callback
     */
    public function __construct(private \Closure $callback)
    {
    }

    /**
     * Up to $length bytes of the next chunk the callback writes, running it
     * until it writes or returns
     */
    public function read(int $length): string
    {
        while ($this->pending === "" && !$this->done) {
            $this->pending = $this->nextChunk();
        }

        $chunk = substr($this->pending, 0, $length);
        $this->pending = substr($this->pending, strlen($chunk));
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        $contents = "";

        while (!$this->eof()) {
            $contents .= $this->read(8192);
        }

        return $contents;
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function eof(): bool
    {
        return $this->done && $this->pending === "";
    }

    public function tell(): int
    {
        return $this->position;
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

    public function close(): void
    {
        $this->detach();
    }

    public function detach()
    {
        $this->done = true;
        $this->pending = "";
        $this->fiber = null;

        return null;
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }

    /**
     * Run the callback until its next write, or "" once it has returned
     */
    private function nextChunk(): string
    {
        $this->fiber ??= new \Fiber(function () {
            ($this->callback)(new StreamWriter(fn(string $chunk) => \Fiber::suspend($chunk)));
        });

        $chunk = $this->fiber->isStarted() ? $this->fiber->resume() : $this->fiber->start();

        if ($this->fiber->isTerminated()) {
            $this->done = true;

            return "";
        }

        return $chunk;
    }
}
