<?php

/**
 * The router script for `dumbo-site dev`, which runs PHP's built-in server
 */

$root = getenv("DUMBO_SITE_ROOT") ?: getcwd();

require "$root/vendor/autoload.php";

Dumbo\Site\Site::load($root)->run();
