---
layout: home

hero:
  name: Dumbo
  text: A lightweight, friendly PHP framework for HTTP.
  tagline:
    - Small framework, big ears.
    - Never forgets a route.
    - Trunk-based routing, literally.
    - Peanut-sized. Elephant-strong.
    - Fewer dependencies than a circus.
  badges:
    - PHP 8.3+
    - Inspired by Hono 🔥
    - MIT licensed
  actions:
    - text: Get started →
      href: /docs/introduction
    - text: Browse examples
      href: /docs/examples
  install: composer require notrab/dumbo
  mascot: 🐘
  code:
    file: index.php
    language: php
    source: |
      <?php

      use Dumbo\Dumbo;

      $app = new Dumbo();

      $app->get('/', function ($context) {
          return $context->text('Hello Dumbo! 🐘');
      });

      $app->get('/peanuts/:count', function ($context) {
          return $context->json([
              'peanuts' => $context->req->param('count'),
          ]);
      });

      $app->run();

features_title: "Everything you need, *nothing you don't*"
features_text: Dumbo packs a surprising amount into a peanut-sized package.
features:
  - icon: 🚀
    title: Lightweight
    details: A handful of small classes on top of FastRoute and PSR-7. No magic, no bloat.
  - icon: 🛣️
    title: Flexible routing
    details: Route params, nested apps and every HTTP method, with 405s handled for you.
  - icon: 🧩
    title: Middleware
    details: Global or path-scoped middleware with a simple $next($context) contract.
  - icon: 🔒
    title: Secure by default
    details: CSRF, JWT, Basic and Bearer auth, body limits and signed cookies built in.
  - icon: ⚡
    title: Batteries included
    details: CORS, compression, HTTP caching, request IDs, logging and static files.
  - icon: 🎨
    title: Bring your templates
    details: Plug in Twig, Blade, Latte or Smarty with one render closure.

cta:
  icon: 🥜
  title: Ready to stomp some routes?
  text: It takes about a minute to go from zero to Hello World.
  action:
    text: Quickstart →
    href: /docs/quickstart
---
