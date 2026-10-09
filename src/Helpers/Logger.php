<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Logger
{
    public const LOG_PREFIX_INCOMING = "-->";
    public const LOG_PREFIX_OUTGOING = "<--";

    private const STATUS_COLORS = [
        1 => 32, // green
        2 => 32, // green
        3 => 36, // cyan
        4 => 33, // yellow
        5 => 31, // red
    ];

    /**
     * Create a middleware that logs each request and its response status and
     * duration to a PSR-3 logger.
     */
    public static function logger(LoggerInterface $logger): callable
    {
        return function (Context $context, callable $next) use ($logger): ResponseInterface {
            $request = $context->req->method() . " " . $context->req->path();

            $logger->info(self::LOG_PREFIX_INCOMING . " " . $request);

            $start = microtime(true);
            $response = $next($context);
            $elapsed = (microtime(true) - $start) * 1000;

            $logger->info(sprintf(
                "%s %s %s %s",
                self::LOG_PREFIX_OUTGOING,
                $request,
                self::colorStatus($response->getStatusCode()),
                $elapsed < 1000 ? round($elapsed, 2) . "ms" : round($elapsed / 1000, 3) . "s"
            ));

            return $response;
        };
    }

    private static function colorStatus(int $status): string
    {
        $color = self::STATUS_COLORS[intdiv($status, 100)] ?? null;

        return $color ? "\033[{$color}m{$status}\033[0m" : (string) $status;
    }
}
