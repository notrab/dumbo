<?php $this->layout("layout", ["title" => $post["title"], "description" => $post["meta"]["description"] ?? null]) ?>

<article>
    <h1><?= e($post["title"]) ?></h1>
    <p class="meta">
        <time><?= date("j F Y", $post["meta"]["date"]) ?></time>
        <?php foreach ($post["meta"]["tags"] ?? [] as $tag): ?>
            <a href="/tags/<?= e($tag) ?>" class="tag"><?= e($tag) ?></a>
        <?php endforeach ?>
    </p>
    <?= $post["html"] ?>
</article>
