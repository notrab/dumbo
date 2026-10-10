<?php

// Sidebar groups, in reading order. Each slug maps to content/{slug}.md,
// except examples, which lists the repo's examples directory
return [
    "Getting started" => [
        "introduction" => "Introduction",
        "quickstart" => "Quickstart",
    ],
    "Concepts" => [
        "routing" => "Routing",
        "context" => "Context",
        "middleware" => "Middleware",
        "error-handling" => "Error handling",
    ],
    "Middleware" => [
        "built-in-middleware" => "Built-in middleware",
    ],
    "Helpers" => [
        "cookies" => "Cookies",
        "jwt" => "JWT",
        "static-files" => "Static files",
    ],
    "Guides" => [
        "templates" => "Templates",
        "environments" => "Environments",
        "deployment" => "Deployment",
        "upgrading" => "Upgrading from 1.x",
    ],
    "Examples" => [
        "examples" => "All examples",
    ],
];
