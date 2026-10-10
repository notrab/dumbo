---
title: Pictures that fit
date: 2026-02-14
description: Local images in Markdown become responsive, lazy loaded WebP.
tags: [jumbo, images]
---

Images in Markdown are resized for every screen, converted to WebP and lazy loaded, with their size set so the page doesn't jump as they arrive:

![A watering hole at dusk](/images/watering-hole.jpg "The watering hole")

Templates do the same with `$site->img()`:

```php
<?= $site->img("/images/watering-hole.jpg", "A watering hole at dusk", width: 720) ?>
```

Only the widths in the `images.widths` config are ever made, and builds write every size the pages use.
