<?php

// Pages show content/{locale}/{slug}.md, falling back to the English copy
$locale = $site->locale($context);
$page = $site->find($slug, $locale);
$page["fallback"] = $locale !== $site->defaultLocale() && $page["slug"] === $slug;

echo $site->view("layout", ["context" => $context, "page" => $page]);
