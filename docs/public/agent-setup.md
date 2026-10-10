# Set up a Dumbo app

You're setting up a PHP web app with Dumbo, a small HTTP framework inspired by Hono. The source and docs live at https://github.com/notrab/dumbo.

## Steps

1. Check that `php -v` reports PHP 8.3 or newer and that `composer --version` works. If either fails, stop and tell me what to install.
2. If the current folder has no `composer.json`, run `composer init --no-interaction --name=app/app`.
3. Run `composer require notrab/dumbo`.
4. Create `index.php` with the starter app below. If `index.php` already exists, ask me before replacing it.
5. Add this to `composer.json` so `composer start` runs the app:

   ```json
   "scripts": {
       "start": [
           "Composer\\Config::disableProcessTimeout",
           "php -S localhost:8000 index.php"
       ]
   }
   ```

6. Start the server in the background with `composer start`, then check that `curl -s localhost:8000` returns `Hello Dumbo!` and `curl -s localhost:8000/health` returns `{"ok":true}`. Stop the server afterwards.
7. Tell me it's running, show me the two routes, and ask what I'd like to build.

## Starter app

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get('/', fn($context) => $context->text('Hello Dumbo!'));

$app->get('/health', fn($context) => $context->json(['ok' => true]));

$app->run();
```

## Dumbo reference

Use these APIs as written. Check the source in `vendor/notrab/dumbo/src` before using anything that isn't listed here.

### Routes

```php
$app->get('/users', $handler);         // also post, put, patch, delete, head, options
$app->all('/ping', $handler);          // every method
$app->get('/users/:id', $handler);     // read with $context->req->param('id')
$app->get('/admin', $handler, [$mw]);  // middleware for one route

$api = new Dumbo();
$api->get('/users', $handler);
$app->route('/api', $api);             // mount after adding the nested app's routes
```

Unknown paths respond with 404, a known path with the wrong method responds with 405, and trailing slashes redirect to the path without one.

### Handlers

A handler receives `$context` and returns a response. Returning an array sends it as JSON.

```php
$context->req->param('id');
$context->req->query('page');          // or query() for all
$context->req->queries('tags');        // every value of ?tags[]=a&tags[]=b
$context->req->body();                 // parsed JSON or form body, as an array
$context->req->header('Authorization');
$context->req->method();
$context->req->path();
$context->req->getUploadedFiles();

return $context->text('Hi', 200);
return $context->json(['ok' => true], 201);
return $context->html('<h1>Hi</h1>');
return $context->redirect('/login');
return $context->send($xml, 'application/xml');

$context->header('X-Powered-By', 'Dumbo');
$context->set('user', $user);          // share values from middleware
$context->get('user');
```

### Middleware

```php
$app->use(function ($context, $next) {
    // before the handler
    $response = $next($context);
    // after the handler
    return $response;
});

$app->use('/admin', $middleware);      // only for /admin and below
```

Built-in middleware is in `Dumbo\Middleware`:

```php
BasicAuth::basicAuth('user:password');
BearerAuth::bearerAuth('secret-token');
CORS::cors(['origin' => 'https://example.com']);
Csrf::csrf(['getToken' => $read, 'setToken' => $write]);
BodyLimit::bodyLimit(1024 * 1024);
Cache::cache(['type' => 'public', 'maxAge' => 3600]);
Compress::compress();
Logger::logger($psrLogger);
RequestId::requestId();
```

Helpers are in `Dumbo\Helpers`: `Cookie::get/set/delete/getSigned/setSigned`, `StaticFiles::serve(__DIR__ . '/public')`, and `JWT::sign/verify/decode`. The JWT helper needs `composer require firebase/php-jwt`.

### Errors and environments

```php
use Dumbo\HTTPException;

throw new HTTPException(404, 'Post not found');

$app->onError(fn($error, $context) => $context->json(['error' => $error->getMessage()], 500));
```

Dumbo runs in development unless `DUMBO_ENV` is set or you pass the environment to `new Dumbo('production')`. Development shows stack traces for uncaught errors, so set `DUMBO_ENV=production` when deploying.

### Templates

Dumbo has no template engine. Set a render closure in middleware, then call `view()`:

```php
$app->use(function ($context, $next) use ($twig) {
    $context->render(fn($view, $data = []) => $twig->render($view, $data));
    return $next($context);
});

$app->get('/', fn($context) => $context->view('home.html', ['name' => 'Dumbo']));
```

The examples folder in the repo has working apps for Twig, Blade, Latte, Smarty, htmx, JWT auth, cookies, Turso and Docker.
