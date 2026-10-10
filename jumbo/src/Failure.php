<?php

namespace Dumbo\Jumbo;

/**
 * A failed action, from Action::fail()
 */
final class Failure
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        public readonly array $errors,
        public readonly int $status = 422,
        public readonly mixed $data = null,
    ) {
        if ($status < 400 || $status > 599) {
            throw new \InvalidArgumentException("A failed action needs an error status, got $status");
        }
    }
}
