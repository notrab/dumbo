<?php $seo = $site->seo(["description" => $description ?? null], $context->req->path(), $title ?? null) ?>
<!DOCTYPE html>
<html lang="<?= e($site->locale($context)) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($seo["title"]) ?></title>
    <meta name="description" content="<?= e($seo["description"]) ?>">
    <?php if ($seo["canonical"]): ?>
        <link rel="canonical" href="<?= e($seo["canonical"]) ?>">
    <?php endif ?>
    <link rel="alternate" type="application/rss+xml" title="<?= e($site->config["name"]) ?>" href="/rss.xml">
    <link rel="icon" href="/favicon.svg">
    <link rel="stylesheet" href="<?= e($site->file("/style.css")) ?>">
    <?= $this->slot("head") ?>
</head>
<body>
    <header>
        <a href="/" class="logo"><?= e($site->config["name"]) ?></a>
        <nav><a href="/about">About</a></nav>
    </header>
    <main><?= $slot ?></main>
</body>
</html>
