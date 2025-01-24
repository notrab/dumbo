<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Middleware\BodyLimitMiddleware;

$app = new Dumbo();

// Limit the body size to 1MB
$app->use(BodyLimitMiddleware::limit(1024 * 1024));

// Or apply it with custom response
// $app->use(BodyLimitMiddleware::limit(1024 * 1024, function($context) {
//     return $context->json(['error' => 'Body too large'], 413);
// }));

$app->post("/upload", function ($context) {
    $file = $context->req->getUploadedFiles()["file"] ?? null;

    if (!$file) {
        return $context->json(["error" => "No file is uploaded"], 400);
    }

    $file->moveTo("uploads/" . $file->getClientFilename());

    return $context->json(["message" => "File is uploaded"]);
});

$app->run();
