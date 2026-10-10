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
    - Four dependencies. Plenty of personality.
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

features_title: "Pick something to *build*"
features_text: Each of these is a few lines of Dumbo. Copy one into index.php and you're away.
features:
  - title: A JSON API for your side project
    details: Routes, params and JSON responses, with a 405 for anyone who tries the wrong method.
    href: /docs/routing
    link: Routing
    code: |
      $app->get('/todos/:id', fn($c) => $c->json(
          $todos->find($c->req->param('id'))
      ));

      $app->post('/todos', fn($c) => $c->json(
          $todos->create($c->req->body()), 201
      ));
  - title: A home for your webhooks
    details: Catch events from GitHub, Stripe or your own services, read the headers and payload, and queue the work for later.
    href: /docs/context
    link: Reading requests
    code: |
      $app->post('/webhooks/github', function ($c) {
          $event = $c->req->header('X-GitHub-Event');
          $queue->push($event, $c->req->body());

          return $c->json(['received' => $event]);
      });
  - title: Server-rendered pages with htmx
    details: Return HTML fragments from your routes and let htmx swap them in. Bring Blade, Twig or Latte when the pages grow.
    href: /docs/templates
    link: Templates
    code: |
      $app->post('/posts/:id/like', function ($c) {
          $posts->like($c->req->param('id'));

          return $c->html(
              '<button disabled>Liked</button>'
          );
      });
  - title: An admin area behind a password
    details: Put Basic auth in front of one path prefix and leave the rest of the app public.
    href: /docs/built-in-middleware#basic-auth
    link: Basic auth
    code: |
      $app->use('/admin', BasicAuth::basicAuth(
          'admin:' . getenv('ADMIN_PASSWORD')
      ));

      $app->get('/admin', fn($c) => $c->html(
          '<h1>Hi boss</h1>'
      ));
  - title: A backend for your frontend
    details: Give a React, Vue or mobile app its own small API with CORS and bearer tokens in front.
    href: /docs/built-in-middleware#cors
    link: CORS and bearer auth
    code: |
      $app->use('/api', CORS::cors([
          'origin' => 'https://myapp.com',
      ]));
      $app->use('/api', BearerAuth::bearerAuth(
          getenv('API_TOKEN')
      ));

      $app->get('/api/me', fn($c) => ['name' => 'Jamie']);
  - title: A docs site, like this one
    details: These docs are a Dumbo app that renders Markdown, and the same routes build a static copy for any CDN.
    href: https://github.com/notrab/dumbo/tree/main/docs
    link: Read the source
    code: |
      $app->get('/docs/:slug', function ($c) use ($site) {
          $page = $site->find($c->req->param('slug'));

          return $page
              ? $c->view('page', ['page' => $page])
              : $c->text('Not found', 404);
      });

cta:
  icon: 🥜
  title: Stomp your first route in about a minute
  text: Install it with Composer, write one route and open your browser.
  action:
    text: Quickstart →
    href: /docs/quickstart
---

## Let your agent set it up

Paste this into Claude Code, Cursor or whichever coding agent you use. It checks your PHP version, installs Dumbo, writes a starter app, runs it to make sure it responds, and then asks what you'd like to build. It also gives the agent a cheat sheet of Dumbo's API, so the code it writes uses methods that exist.

::: prompt file="agent-setup.md" title="Set up a Dumbo app"
:::

Agents that can fetch URLs can read the same prompt from [/agent-setup.md](/agent-setup.md).

