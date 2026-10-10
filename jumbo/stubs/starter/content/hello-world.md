---
date: 2026-01-01
description: The first post.
---

# Hello, world

Posts are Markdown files in `content/`, and `pages/blog/[slug].php` builds a page for each one.

```php
$app->get("/", fn($context) => $context->text("Code is highlighted on the server"));
```
