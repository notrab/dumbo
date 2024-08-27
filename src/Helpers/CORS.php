<?php

namespace Dumbo\Helpers;

use Dumbo\Context;

class CORS
{
    /**
     * Configure CORS headers.
     *
     * @param array $opts optional - The CORS options
     * @return callable The middleware function for handling CORS.
     */
    public static function cors(array $opts = []): callable
    {
        return function (Context $c, callable $next) use ($opts) {
            $origin = $c->req->header('origin');

            $allowOrigin = $opts['origin'] ?? '*';
            $allowMethods = $opts['allow_methods'] ?? ['GET', 'HEAD', 'PUT', 'POST', 'DELETE', 'PATCH'];

            if (is_callable($allowOrigin)) {
                $allowOrigin = $allowOrigin($origin, $c);
            } else if (is_array($allowOrigin) && array_is_list($allowOrigin)) {
                $allowOrigin = in_array($origin, $allowOrigin) ? $origin : $allowOrigin[0];
            }

            if (!empty($allowOrigin)) {
                $c->header('Access-Control-Allow-Origin', $allowOrigin);
            }

            if ($opts['credentials']) {
                $c->header('Access-Control-Allow-Credentials', 'true');
            }
            if (!empty($opts['expose_headers'])) {
                $c->header('Access-Control-Expose-Headers', implode(', ', $opts['expose_headers']));
            }

            if ($c->req->method() === 'OPTIONS') {
                if (!empty($opts['max_age'])) {
                    $c->header('Access-Control-Max-Age', (string) $opts['max_age']);
                }

                if (!empty($allowMethods)) {
                    $c->header('Access-Control-Allow-Methods', implode(', ', $allowMethods));
                }

                $headers = $opts['allow_headers'];
                if (empty($headers)) {
                    $reqHeaders = $c->req->header('Access-Control-Request-Headers');
                    if (!empty($reqHeaders)) {
                        $headers = array_map('trim', explode(',', $reqHeaders));
                    }
                }
                if (!empty($headers)) {
                    $c->header('Access-Control-Allow-Headers', implode(', ', $opts['allow_headers']));
                }

                return $c->getResponse()
                    ->withStatus(204)
                    ->withoutHeader('Content-Length')
                    ->withoutHeader('Content-Type');
            }

            return $next($c);
        };
    }
}
