<?php

namespace Dumbo\Tests;

use Dumbo\Context;
use Dumbo\Dumbo;
use Dumbo\Middleware\Cache;
use Dumbo\Middleware\Compress;
use Dumbo\Stream\CallbackStream;
use Dumbo\Stream\StreamWriter;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase
{
    private function sse(callable $write): string
    {
        $output = "";
        $write(new StreamWriter(function (string $chunk) use (&$output) {
            $output .= $chunk;
        }));

        return $output;
    }

    public function testWriteSSEFormatsFieldsAndData()
    {
        $output = $this->sse(fn(StreamWriter $s) => $s->writeSSE("hello", event: "greeting", id: "1", retry: 500));

        $this->assertSame("event: greeting\nid: 1\nretry: 500\ndata: hello\n\n", $output);
    }

    public function testWriteSSESplitsMultilineDataAndEncodesArrays()
    {
        $this->assertSame(
            "data: one\ndata: two\n\n",
            $this->sse(fn(StreamWriter $s) => $s->writeSSE("one\ntwo"))
        );
        $this->assertSame(
            "data: {\"a\":1}\n\n",
            $this->sse(fn(StreamWriter $s) => $s->writeSSE(["a" => 1]))
        );
    }

    public function testWriteSSEStripsNewlinesFromFields()
    {
        $output = $this->sse(fn(StreamWriter $s) => $s->writeSSE("x", event: "a\nb"));

        $this->assertSame("event: ab\ndata: x\n\n", $output);
    }

    public function testPipeSendsEachChunkAsItIsWritten()
    {
        $chunks = [];
        $stream = new CallbackStream(function (StreamWriter $s) {
            $s->write("one")->write("two");
        });

        $stream->pipe(function (string $chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $this->assertSame(["one", "two"], $chunks);
        $this->assertTrue($stream->eof());
    }

    public function testCallbackRunsOnceWhenReadThenPiped()
    {
        $runs = 0;
        $stream = new CallbackStream(function (StreamWriter $s) use (&$runs) {
            $runs++;
            $s->write("body");
        });

        $this->assertSame("bo", $stream->read(2));

        $piped = "";
        $stream->pipe(function (string $chunk) use (&$piped) {
            $piped .= $chunk;
        });

        $this->assertSame("dy", $piped);
        $this->assertSame("body", (string) $stream);
        $this->assertSame(1, $runs);
    }

    public function testStreamSetsContentTypeAndBody()
    {
        $app = new Dumbo();
        $app->get("/", fn(Context $c) => $c->stream(function (StreamWriter $s) {
            $s->writeln("a")->writeln("b");
        }, "text/plain"));

        $response = $app->handle(new ServerRequest("GET", "/"));

        $this->assertSame("text/plain", $response->getHeaderLine("Content-Type"));
        $this->assertSame("a\nb\n", (string) $response->getBody());
    }

    public function testStreamSSESetsEventStreamHeaders()
    {
        $app = new Dumbo();
        $app->get("/events", fn(Context $c) => $c->streamSSE(function (StreamWriter $s) {
            $s->writeSSE("tick");
        }));

        $response = $app->handle(new ServerRequest("GET", "/events"));

        $this->assertSame("text/event-stream", $response->getHeaderLine("Content-Type"));
        $this->assertSame("no-cache", $response->getHeaderLine("Cache-Control"));
        $this->assertSame("no", $response->getHeaderLine("X-Accel-Buffering"));
        $this->assertSame("data: tick\n\n", (string) $response->getBody());
    }

    public function testCompressAndCacheLeaveStreamsAlone()
    {
        $runs = 0;
        $app = new Dumbo();
        $app->use(Compress::compress(["threshold" => 0]));
        $app->use(Cache::cache());
        $app->get("/events", fn(Context $c) => $c->streamSSE(function (StreamWriter $s) use (&$runs) {
            $runs++;
            $s->writeSSE(str_repeat("x", 2048));
        }));

        $response = $app->handle(
            new ServerRequest("GET", "/events", ["Accept-Encoding" => "gzip"])
        );

        $this->assertSame(0, $runs);
        $this->assertFalse($response->hasHeader("Content-Encoding"));
        $this->assertFalse($response->hasHeader("ETag"));
    }
}
