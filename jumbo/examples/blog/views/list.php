<ul class="posts">
    <?php foreach ($posts as $post): ?>
        <li>
            <a href="/posts/<?= e($post["slug"]) ?>"><?= e($post["title"]) ?></a>
            <time><?= date("j M Y", $post["meta"]["date"]) ?></time>
        </li>
    <?php endforeach ?>
</ul>
