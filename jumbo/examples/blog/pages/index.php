<?php $this->layout("layout") ?>

<section class="hero">
    <?= $site->img("/images/watering-hole.jpg", "A watering hole at dusk", width: 720, attributes: ["loading" => "eager", "fetchpriority" => "high"]) ?>
    <h1><?= e($site->config["seo"]["description"]) ?></h1>
</section>

<h2>Latest</h2>

<?= $site->view("list", ["posts" => array_slice($site->posts(), 0, 3)]) ?>

<p class="tags">
    <?php foreach ($site->tags() as $tag): ?>
        <a href="/tags/<?= e($tag) ?>" class="tag"><?= e($tag) ?></a>
    <?php endforeach ?>
</p>
