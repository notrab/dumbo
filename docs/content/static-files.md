# Static files

The `Dumbo\Helpers\StaticFiles` helper serves files from a directory, with the right `Content-Type`, an `ETag` and `304 Not Modified` responses. These docs serve their CSS with it.

## As middleware

Serve any file that matches the request path, and fall through to your routes when nothing matches:

```php
use Dumbo\Helpers\StaticFiles;

$app->use(StaticFiles::serve(__DIR__ . '/public'));

$app->get('/', fn($context) => $context->text('Home'));
```

A request for `/css/app.css` serves `public/css/app.css`. Directories serve their `index.html`.

## As a route

Use it as a route handler with a `path` param to serve files under a prefix:

```php
$app->get('/assets/:path', StaticFiles::serve(__DIR__ . '/assets'));
```

Unknown files respond with a `404`.

## Security

Requests can't escape the directory. Paths like `/../secret.txt` resolve outside it and are refused.
