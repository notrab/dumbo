<?php

/**
 * The router script for `jumbo dev`, which runs PHP's built-in server
 */

$root = getenv("JUMBO_ROOT") ?: getcwd();

require "$root/vendor/autoload.php";

$site = Dumbo\Jumbo\Site::load($root);

if (getenv("JUMBO_WATCH") !== "0") {
    $site->watch();
}

$site->run();
