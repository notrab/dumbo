<?php

namespace Dumbo\Helpers;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Logger
{
    public const LOG_PREFIX_INCOMING = "-->";
    public const LOG_PREFIX_OUTGOING = "<--";

    private LoggerInterface $logger;

    /**
     * Constructor to initialize the logger with a PSR-3 compliant logger.
     *
     * @param LoggerInterface $logger The PSR-3 compliant logger.
     */
    private function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Create a middleware that logs incoming requests.
     *
     * @param LoggerInterface $logger The PSR-3 compliant logger.
     * @return callable The middleware.
     */
    public static function logger(LoggerInterface $logger): callable
    {
        $middleware = new self($logger);
        return $middleware;
    }

    /**
     * Invoke the logger middleware.
     *
     * @param Context $context The context object containing the request.
     * @param callable $next The next middleware or handler.
     * @return ResponseInterface The HTTP response.
     */
    public function __invoke(
        Context $context,
        callable $next
    ): ResponseInterface {
        $method = $context->req->method();
        $path = $context->req->path();

        $this->log(self::LOG_PREFIX_INCOMING, $method, $path);

        $start = microtime(true);
        $response = $next($context);
        $elapsed = $this->getElapsedTime($start);

        $this->log(
            self::LOG_PREFIX_OUTGOING,
            $method,
            $path,
            $response->getStatusCode(),
            $elapsed
        );

        return $response;
    }

    /**
     * Log a message with the specified prefix, method, path, status, and elapsed time.
     *
     * @param string $prefix The log prefix indicating incoming or outgoing.
     * @param string $method The HTTP method of the request.
     * @param string $path The request path.
     * @param int $status The HTTP status code (default is 0).
     * @param string $elapsed The elapsed time for processing the request (default is an empty string).
     * @return void
     */
    private function log(
        string $prefix,
        string $method,
        string $path,
        int $status = 0,
        string $elapsed = ""
    ): void {
        $message =
            $prefix === self::LOG_PREFIX_INCOMING
                ? sprintf("%s %s %s", $prefix, $method, $path)
                : sprintf(
                    "%s %s %s %s %s",
                    $prefix,
                    $method,
                    $path,
                    $this->colorStatus($status),
                    $elapsed
                );

        $this->logger->info($message);
    }

    /**
     * Calculate the elapsed time since the given start time.
     *
     * @param float $start The start time in microseconds.
     * @return string The elapsed time in milliseconds or seconds.
     */
    private function getElapsedTime(float $start): string
    {
        $delta = (microtime(true) - $start) * 1000;
        return $delta < 1000 ? $delta . "ms" : round($delta / 1000, 3) . "s";
    }

    /**
     * Get a colored string representation of the HTTP status code.
     *
     * @param int $status The HTTP status code.
     * @return string The colored status code.
     */
    private function colorStatus(int $status): string
    {
        $colors = [
            7 => "\033[35m%d\033[0m",
            5 => "\033[31m%d\033[0m",
            4 => "\033[33m%d\033[0m",
            3 => "\033[36m%d\033[0m",
            2 => "\033[32m%d\033[0m",
            1 => "\033[32m%d\033[0m",
            0 => "\033[33m%d\033[0m",
        ];

        $colorCode = $colors[intdiv($status, 100)] ?? "%d";
        return sprintf($colorCode, $status);
    }
}
