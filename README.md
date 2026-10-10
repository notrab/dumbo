<p align="center">
  <img src=".github/dumbo.jpeg" alt="Dumbo" width="600"/>
  <h1 align="center">Dumbo</h1>
</p>

<p align="center">
  A lightweight, friendly PHP framework for HTTP &mdash; Inspired by Hono.
</p>

<p align="center">
  <a href="https://discord.gg/nAq2h9BfsU">
    <picture>
      <img src="https://img.shields.io/discord/1278637768918171709?color=8A2BE2" alt="Discord" />
    </picture>
  </a>
  <picture>
    <img src="https://img.shields.io/github/contributors/notrab/dumbo?color=8A2BE2" alt="Contributors" />
  </picture>
  <a href="https://packagist.org/packages/notrab/dumbo">
    <picture>
      <img src="https://img.shields.io/packagist/dt/notrab/dumbo?color=8A2BE2" alt="Total downloads" />
    </picture>
  </a>
  <a href="https://notrab.github.io/dumbo">
    <img src="https://img.shields.io/badge/read-docs-8A2BE2" alt="Docs" />
  </a>
</p>

## Install

```bash
composer require notrab/dumbo
```

## Quickstart

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$app = new Dumbo\Dumbo();

$app->get('/', fn($context) => $context->json(['message' => 'Hello, Dumbo!']));

$app->get('/users/:id', fn($context) => $context->json([
    'id' => $context->req->param('id'),
]));

$app->run();
```

```bash
php -S localhost:8000 index.php
```

Routing, middleware, helpers, deployment and upgrading from 1.x are all in the [docs](https://notrab.github.io/dumbo). There are runnable apps in [`examples/`](examples).

## License

Dumbo is open-sourced software licensed under the [MIT license](LICENSE).

## Contributors

![Contributors](https://contrib.nn.ci/api?repo=notrab/dumbo)
