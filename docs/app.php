<?php

/**
 * The docs site. index.php serves it, and `composer build` renders its pages
 * to static files. Pages are in pages/, and config.php has the settings.
 */

require __DIR__ . "/vendor/autoload.php";

return new Docs\Site(__DIR__, require __DIR__ . "/config.php");
