<?php $this->layout("layout", ["title" => $post["title"], "description" => $post["meta"]["description"] ?? null]) ?>

<article>
    <?php if (isset($post["meta"]["date"])): ?>
        <time><?= e(date("j F Y", is_int($post["meta"]["date"]) ? $post["meta"]["date"] : strtotime($post["meta"]["date"]))) ?></time>
    <?php endif ?>
    <?= $post["html"] ?>
</article>
