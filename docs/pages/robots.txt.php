<?php $url = rtrim($site->config["url"] ?? "", "/"); ?>
User-agent: *
Allow: /
<?php if ($url): ?>

Sitemap: <?= $url ?>/sitemap.xml
<?php endif ?>
