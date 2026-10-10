<?php $seo = $site->seo($page["meta"], $context->req->path(), $context->req->path() === "/" ? null : $page["title"]) ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($seo["title"]) ?></title>
    <?php if ($seo["canonical"]): ?>
        <link rel="canonical" href="<?= e($seo["canonical"]) ?>">
    <?php endif ?>
    <?php if ($seo["noindex"]): ?>
        <meta name="robots" content="noindex">
    <?php endif ?>
    <link rel="stylesheet" href="<?= e($site->file("/style.css")) ?>">
</head>
<body>
    <nav><a href="/">Handbook</a> <a href="/guides/first-steps">First steps</a> <a href="/guides/peanuts">Peanuts</a></nav>
    <main><?= $page["html"] ?></main>
</body>
</html>
