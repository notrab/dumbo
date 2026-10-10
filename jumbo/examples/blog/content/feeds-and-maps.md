---
title: Feeds and maps
date: 2026-01-20
description: An RSS feed from one page, and a sitemap for free.
tags: [jumbo, seo]
---

The feed at `/rss.xml` is one small page, `pages/rss.xml.php`, that hands the posts to `$site->rss()`. The channel's title, link and description come from `app.php`.

Builds also write `sitemap.xml`, listing every page that isn't marked `noindex`. There's nothing to set up beyond the site's `url`.
