---
title: Routing from files
date: 2026-03-02
description: Every file in pages/ is a route, and brackets make params.
tags: [jumbo, routing]
---

Every file in `pages/` is a route. `pages/about.md` is `/about`, and brackets make params, so `pages/posts/[slug].php` serves every post on this blog.

A page with params lists the ones to build, and can bring props along so it doesn't have to look anything up twice:

```php
return new Page(
    paths: fn(Blog $site) => array_map(
        fn(array $post) => ["params" => ["slug" => $post["slug"]], "props" => ["post" => $post]],
        $site->posts()
    ),
    render: fn($context, $site, $props) => $site->view("post", ["post" => $props["post"]]),
);
```

::: aside title="Psst"
This box is a component in `components/aside.php`. Its CSS is hoisted into the head once, however many asides a page has.
:::
