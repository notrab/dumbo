<?php

require __DIR__ . "/vendor/autoload.php";

return new Dumbo\Jumbo\Site(__DIR__, [
    "name" => "Elephant Sanctuary",
    "url" => getenv("SITE_URL") ?: "https://sanctuary.example",
    // English at /, French at /fr and Spanish at /es. Set prefixDefault to
    // put English at /en too.
    "i18n" => [
        "locales" => ["en", "fr", "es"],
        "default" => "en",
    ],
]);
