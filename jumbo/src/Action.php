<?php

namespace Dumbo\Jumbo;

/**
 * What a page's action did with a form, for the page to show. Pages read it
 * with $site->action($context):
 *
 *   <?php $action = $site->action($context) ?>
 *   <input name="email" value="<?= e($action?->old("email")) ?>">
 *   <?= e($action?->error("email")) ?>
 */
final class Action
{
    /**
     * @param string $name The action that ran
     * @param array $input The submitted fields
     * @param mixed $data What the action returned, or the data it failed with
     * @param array<string, string> $errors Messages by field, empty when it worked
     */
    public function __construct(
        public readonly string $name,
        public readonly array $input = [],
        public readonly mixed $data = null,
        public readonly array $errors = [],
        public readonly int $status = 200,
    ) {
    }

    /**
     * Fail an action, so the page shows again with the errors and what was
     * submitted. Return it from the action.
     *
     * @param array<string, string> $errors Messages by field, or "form" for the whole form
     */
    public static function fail(array $errors, int $status = 422, mixed $data = null): Failure
    {
        return new Failure($errors, $status, $data);
    }

    public function ok(): bool
    {
        return $this->errors === [];
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * A submitted value, to fill a form back in after it fails
     */
    public function old(string $field, mixed $default = ""): mixed
    {
        return $this->input[$field] ?? $default;
    }
}
