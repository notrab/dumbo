<?php $this->layout("layout", ["title" => $page["title"], "description" => $page["meta"]["description"] ?? null]) ?>

<?= $page["html"] ?>
