<?php
$locale = $site->locale($context);
$t = (require __DIR__ . "/strings.php")[$locale];
$names = ["en" => "English", "fr" => "Français", "es" => "Español"];
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page["title"]) ?> · <?= e($site->config["name"]) ?></title>
    <?php // Tell search engines about every translation of this page ?>
    <?php foreach ($site->alternates($context) as $alternate => $path): ?>
        <link rel="alternate" hreflang="<?= e($alternate) ?>" href="<?= e($site->url($path)) ?>">
    <?php endforeach ?>
    <link rel="stylesheet" href="<?= e($site->file("/style.css")) ?>">
</head>
<body>
    <header>
        <nav>
            <a href="<?= e($site->localize("/", $locale)) ?>"><?= e($t["home"]) ?></a>
            <a href="<?= e($site->localize("/visit", $locale)) ?>"><?= e($t["visit"]) ?></a>
        </nav>
        <nav aria-label="<?= e($t["language"]) ?>">
            <?php foreach ($site->alternates($context) as $alternate => $path): ?>
                <a href="<?= e($path) ?>" hreflang="<?= e($alternate) ?>" <?= $alternate === $locale ? 'aria-current="true"' : "" ?>><?= e($names[$alternate]) ?></a>
            <?php endforeach ?>
        </nav>
    </header>
    <main>
        <?php if ($page["fallback"]): ?><p class="note"><?= e($t["missing"]) ?></p><?php endif ?>
        <?= $page["html"] ?>
    </main>
</body>
</html>
