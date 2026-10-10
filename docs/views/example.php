<p class="not-prose mb-2"><a href="/docs/examples" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-300">&larr; All examples</a></p>

<h1><?= e($example["title"]) ?></h1>

<?= $readme ?: "<p>" . inline($example["description"]) . "</p>" ?>

<div class="not-prose my-8 flex flex-wrap gap-3">
    <a href="<?= e($githubUrl) ?>" class="inline-flex items-center gap-2 rounded-full bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .5a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2.2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1.1-.7.1-.7.1-.7 1.2.1 1.9 1.2 1.9 1.2 1.1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2-.1-.3-.5-1.5.1-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0C17.3 4.7 18.3 5 18.3 5c.7 1.7.2 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .5Z"/></svg>
        View on GitHub
    </a>
</div>

<?php foreach ($files as $file): ?>
    <h2><?= e($file["name"]) ?></h2>
    <pre><code class="language-<?= $file["name"] === "Dockerfile" ? "dockerfile" : "php" ?>"><?= e($file["source"]) ?></code></pre>
<?php endforeach ?>
