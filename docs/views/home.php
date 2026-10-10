<main class="overflow-hidden">
    <section class="relative mx-auto max-w-6xl px-4 pb-16 pt-16 sm:px-6 sm:pt-24 lg:flex lg:items-center lg:gap-12 lg:pt-32">
        <div class="pointer-events-none absolute -top-24 right-0 -z-10 h-[28rem] w-[28rem] rounded-full bg-brand-500/20 blur-3xl lg:right-10"></div>

        <div class="flex-1 text-center lg:text-left">
            <h1 class="text-5xl font-extrabold tracking-tight sm:text-7xl">
                <span class="bg-linear-to-r from-brand-500 to-fuchsia-500 bg-clip-text text-transparent">Dumbo</span>
            </h1>
            <p class="mt-4 text-2xl font-bold text-zinc-900 sm:text-4xl dark:text-white">
                A lightweight, friendly PHP framework for HTTP
            </p>
            <p class="mt-4 text-lg text-zinc-500 dark:text-zinc-400">
                Fast routing, composable middleware and helpers you'll actually use. Inspired by Hono.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
                <a href="/docs/introduction" class="rounded-full bg-brand-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 transition hover:bg-brand-600">Get started</a>
                <a href="https://github.com/notrab/dumbo" class="rounded-full bg-zinc-100 px-6 py-3 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700">View on GitHub</a>
            </div>
            <div class="prose mx-auto mt-8 max-w-md lg:mx-0">
                <pre class="my-0! py-3! text-left"><code class="language-bash">composer require notrab/dumbo</code></pre>
            </div>
        </div>

        <div class="prose mt-12 min-w-0 max-w-none flex-1 lg:mt-0">
            <pre class="my-0! text-sm shadow-2xl shadow-brand-500/10"><code class="language-php">&lt;?php

use Dumbo\Dumbo;

$app = new Dumbo();

$app-&gt;get('/', function ($context) {
    return $context-&gt;text('Hello Dumbo!');
});

$app-&gt;get('/users/:id', function ($context) {
    return $context-&gt;json([
        'id' =&gt; $context-&gt;req-&gt;param('id'),
    ]);
});

$app-&gt;run();</code></pre>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-24 sm:px-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ([
                ["🚀", "Lightweight", "A handful of small classes on top of FastRoute and PSR-7. No magic, no bloat."],
                ["🛣️", "Flexible routing", "Route params, nested apps and every HTTP method, with 405s handled for you."],
                ["🧩", "Middleware", "Global or path-scoped middleware with a simple \$next(\$context) contract."],
                ["🔒", "Secure by default", "CSRF, JWT, Basic and Bearer auth, body limits and signed cookies built in."],
                ["⚡", "Batteries included", "CORS, compression, HTTP caching, request IDs, logging and static files."],
                ["🎨", "Bring your templates", "Plug in Twig, Blade, Latte or Smarty with one render closure."],
            ] as [$icon, $heading, $text]): ?>
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-6 dark:border-zinc-800 dark:bg-zinc-900/50">
                    <div class="text-3xl"><?= $icon ?></div>
                    <h3 class="mt-4 font-semibold text-zinc-900 dark:text-white"><?= e($heading) ?></h3>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400"><?= e($text) ?></p>
                </div>
            <?php endforeach ?>
        </div>
    </section>

    <footer class="border-t border-zinc-200 py-8 text-center text-sm text-zinc-500 dark:border-zinc-800">
        Released under the MIT License.
    </footer>
</main>
