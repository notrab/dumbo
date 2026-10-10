<?php $this->layout("base", ["title" => "Home"]) ?>
<?php $this->start("head") ?><meta name="page" content="home"><?php $this->end() ?>
<h1>Welcome to <?= e($site->config["name"]) ?></h1>
