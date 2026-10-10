<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($seo["title"] ?? $site->seo([], "/", $title ?? null)["title"]) ?></title>
    <link rel="stylesheet" href="<?= e($site->file("/style.css")) ?>">
</head>
<body>
    <main><?= $page["html"] ?? $slot ?></main>
</body>
</html>
