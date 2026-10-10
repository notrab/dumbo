<?php

require __DIR__ . "/vendor/autoload.php";

return new Dumbo\Jumbo\Site(__DIR__, [
    "name" => "Peanut Supply Co.",
    "seo" => ["titleTemplate" => "%s - Peanut Supply Co."],
]);
