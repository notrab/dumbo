<?php $this->layout("layout", ["title" => "Archive, page $page->current"]) ?>

<h1>Archive</h1>

<p>Posts <?= $page->start() ?> to <?= $page->end() ?> of <?= $page->total ?></p>

<?= $site->view("list", ["posts" => $page->items]) ?>

<nav class="pagination">
    <?php if ($page->previous()): ?><a href="<?= e($page->previous()) ?>">← Newer</a><?php endif ?>
    <span>Page <?= $page->current ?> of <?= $page->last ?></span>
    <?php if ($page->next()): ?><a href="<?= e($page->next()) ?>">Older →</a><?php endif ?>
</nav>
