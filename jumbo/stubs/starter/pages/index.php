<?php $this->layout("layout") ?>

<h1>Welcome to <?= e($site->config["name"]) ?></h1>

<p>Edit <code>pages/index.php</code> and this page reloads as you save.</p>

<h2>Posts</h2>

<ul>
    <?php foreach ($site->slugs() as $slug): ?>
        <li><a href="/blog/<?= e($slug) ?>"><?= e($site->find($slug)["title"]) ?></a></li>
    <?php endforeach ?>
</ul>
