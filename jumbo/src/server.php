<?php

/**
 * The router script for `jumbo dev`, which runs PHP's built-in server
 */

$root = getenv("JUMBO_ROOT") ?: getcwd();

require "$root/vendor/autoload.php";

Dumbo\Jumbo\Site::load($root)->run();
