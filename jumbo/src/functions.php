<?php

if (!function_exists("e")) {
    /**
     * Escape a value for HTML
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? "", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }
}
