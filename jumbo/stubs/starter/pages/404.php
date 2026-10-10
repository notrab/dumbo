<?php $this->layout("layout", ["title" => "Not found"]) ?>

<h1>Not found</h1>

<p>There's nothing at <code><?= e($context->req->path()) ?></code>. <a href="/">Go home</a>.</p>
