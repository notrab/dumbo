<?php

namespace Dumbo\Middleware;

use Dumbo\Context;
use Psr\Http\Message\ResponseInterface;

class Validator
{
    public const TARGETS = ["json", "form", "query", "param", "header", "cookie"];

    /**
     * Create a middleware that validates part of the request.
     *
     * The callback is called with ($value, $context), where $value is the
     * target's data as an array. Return a response to reject the request, or
     * the validated data, which handlers read with $context->req->valid($target).
     *
     * @param string $target One of json, form, query, param, header or cookie
     * @param callable(array, Context): mixed $validate
     *
     * @throws \InvalidArgumentException If the target is unknown
     */
    public static function validator(string $target, callable $validate): callable
    {
        if (!in_array($target, self::TARGETS, true)) {
            throw new \InvalidArgumentException(
                "Unknown validation target \"$target\", expected one of " . implode(", ", self::TARGETS)
            );
        }

        return function (Context $context, callable $next) use ($target, $validate): ResponseInterface {
            $result = $validate(self::value($context, $target), $context);

            if ($result instanceof ResponseInterface) {
                return $result;
            }

            $context->req->setValid($target, $result);

            return $next($context);
        };
    }

    private static function value(Context $context, string $target): array
    {
        $req = $context->req;
        $contentType = $req->header("Content-Type") ?? "";

        return match ($target) {
            "json" => str_contains($contentType, "application/json") ? $req->body() : [],
            "form" => preg_match('#application/x-www-form-urlencoded|multipart/form-data#', $contentType)
                ? $req->body()
                : [],
            "query" => $req->query(),
            "param" => $req->param(),
            "header" => array_map(
                fn(array $values) => $values[0] ?? null,
                array_change_key_case($req->headers(), CASE_LOWER)
            ),
            "cookie" => $req->cookie(),
        };
    }
}
