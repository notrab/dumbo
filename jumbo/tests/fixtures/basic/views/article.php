<?php $this->layout("html", ["title" => $seo["title"]]) ?>
<article><?= $page["html"] ?></article>
<?php foreach ($page["toc"] as $item): ?><a href="#<?= e($item["id"]) ?>"><?= e($item["text"]) ?></a><?php endforeach ?>
