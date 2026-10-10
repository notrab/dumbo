<?php $logo = $site->config["logo"]; ?>
<?php if (preg_match('/\.(svg|png|jpe?g|webp|gif)$/i', $logo)): ?>
    <img src="<?= e($logo) ?>" alt="" class="inline-block h-[1em] w-auto <?= $class ?? "" ?>">
<?php else: ?>
    <span class="inline-block <?= $class ?? "" ?>"><?= e($logo) ?></span>
<?php endif ?>
