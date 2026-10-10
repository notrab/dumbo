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

    /** Holds the chunk the callback last wrote, shared with the fiber */
    private \stdClass $written;

    private ?string $contents = null;
    private string $pending = "";
    private bool $done = false;
    private int $position = 0;

    /**
     * @param \Closure(StreamWriter): void $callback
     */
    public function __construct(private \Closure $callback)
    {
        $this->written = new \stdClass();
        $this->written->chunk = null;
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

    /**
     * The rest of the body, kept so that casting again returns it again
     */
    public function __toString(): string
    {
        return $this->contents ??= $this->getContents();
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
        $this->finish();
        $this->pending = "";

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
        // Capture only what the fiber needs, so it doesn't keep $this alive
        $callback = $this->callback;
        $written = $this->written;

        $this->fiber ??= new \Fiber(static function () use ($callback, $written) {
            $fiber = \Fiber::getCurrent();

            $callback(new StreamWriter(static function (string $chunk) use ($fiber, $written) {
                if (\Fiber::getCurrent() !== $fiber) {
                    throw new \LogicException("Write to a stream only from inside its callback");
                }

                $written->chunk = $chunk;
                \Fiber::suspend();
            }));
        });

        try {
            $this->fiber->isStarted() ? $this->fiber->resume() : $this->fiber->start();
        } catch (\Throwable $error) {
            $this->finish();

            throw $error;
        }

        if ($this->fiber->isTerminated()) {
            $this->finish();

            return "";
        }

        if ($this->written->chunk === null) {
            $this->finish();

            throw new \LogicException("Stream callbacks run in a Fiber and can't suspend it, e.g. for async I/O");
        }

        $chunk = $this->written->chunk;
        $this->written->chunk = null;

        return $chunk;
    }

    private function finish(): void
    {
        $this->done = true;
        $this->fiber = null;
    }
}
