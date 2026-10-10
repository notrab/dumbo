<?php $seo = $site->seo(["description" => $description ?? null], $context->req->path(), $title ?? null) ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($seo["title"]) ?></title>
    <meta name="description" content="<?= e($seo["description"]) ?>">
    <?php if ($seo["canonical"]): ?>
        <link rel="canonical" href="<?= e($seo["canonical"]) ?>">
    <?php endif ?>
    <link rel="alternate" type="application/rss+xml" title="<?= e($site->config["name"]) ?>" href="/rss.xml">
    <link rel="stylesheet" href="<?= e($site->file("/style.css")) ?>">
    <style><?= $site->highlightStyles("github-light") ?></style>
</head>
<body>
    <header>
        <a href="/" class="logo">🐘 <?= e($site->config["name"]) ?></a>
        <nav>
            <a href="/blog/1">Archive</a>
            <a href="/rss.xml">RSS</a>
        </nav>
    </header>
    <main><?= $slot ?></main>
    <footer>Built with Jumbo</footer>
</body>
</html>
