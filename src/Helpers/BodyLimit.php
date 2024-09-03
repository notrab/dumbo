<?php

namespace Dumbo\Helpers;

use Dumbo\Context;

class BodyLimit
{
    public static function limit(int $maxSize, callable $onError = null): callable
    {
        return function (Context $context, callable $next) use ($maxSize, $onError) {
            $contentLength = $context->req->header('Content-Length');

            if ($contentLength > $maxSize) {
                return is_null($onError) ? throw new \Exception("Body too large", 413) : $onError($context);
            }

            return $next($context);
        };
    }
}
