# Templates

Dumbo doesn't ship a template engine. Instead, give the context a render closure with `render()`, then call `view()` from your handlers. The result is sent as HTML.

## Twig

```bash
composer require twig/twig
```

```php
$twig = new Twig\Environment(new Twig\Loader\FilesystemLoader(__DIR__ . '/views'));

$app->use(function ($context, $next) use ($twig) {
    $context->render(fn(string $view, array $data = []) => $twig->render($view, $data));

    return $next($context);
});

$app->get('/', function ($context) {
    return $context->view('home.html', ['message' => 'Hello Dumbo']);
});
```

## Any engine

`view()` passes its arguments straight to your closure, so any engine works. Here's plain PHP:

```php
$app->use(function ($context, $next) {
    $context->render(function (string $view, array $data = []) {
        extract($data);
        ob_start();
        require __DIR__ . "/views/$view.php";

        return ob_get_clean();
    });

    return $next($context);
});
```

These docs are built exactly this way.

## Examples

There are complete examples for each popular engine:

- [Twig](https://github.com/notrab/dumbo/tree/main/examples/context-view-twig)
- [Blade](https://github.com/notrab/dumbo/tree/main/examples/context-view-blade)
- [Latte](https://github.com/notrab/dumbo/tree/main/examples/context-view-latte)
- [Smarty](https://github.com/notrab/dumbo/tree/main/examples/context-view-smarty)
- [htmx with Blade](https://github.com/notrab/dumbo/tree/main/examples/htmx-blade)
