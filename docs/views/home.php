<main class="overflow-hidden">
    <section class="relative border-b-2 border-ink bg-[radial-gradient(var(--color-ink)_1px,transparent_1px)] [background-size:22px_22px]">
        <div class="absolute inset-0 bg-linear-to-b from-paper/60 via-paper/85 to-paper"></div>

        <div class="relative mx-auto max-w-6xl px-4 pt-14 pb-16 sm:px-6 sm:pt-20 lg:flex lg:items-center lg:gap-14 lg:pt-24 lg:pb-24">
            <div class="flex-1 text-center lg:text-left">
                <div class="flex flex-wrap justify-center gap-2 lg:justify-start">
                    <span class="-rotate-3 rounded-full border-2 border-ink bg-peanut-300 px-3 py-1 text-xs font-bold text-[#1c1917]">PHP 8.3+</span>
                    <span class="rotate-2 rounded-full border-2 border-ink bg-brand-200 px-3 py-1 text-xs font-bold text-[#1c1917]">Inspired by Hono 🔥</span>
                    <span class="-rotate-1 rounded-full border-2 border-ink bg-white px-3 py-1 text-xs font-bold text-[#1c1917]">MIT licensed</span>
                </div>

                <h1 class="mt-6 font-display text-6xl font-extrabold tracking-tight text-ink sm:text-8xl">
                    <span class="highlight">Dumbo</span>
                </h1>
                <p class="mt-4 font-display text-2xl font-bold tracking-tight text-ink sm:text-4xl">
                    A lightweight, friendly PHP framework for HTTP.
                </p>
                <p class="mt-4 h-7 text-lg text-stone-600 dark:text-stone-400">
                    <span data-tagline class="inline-block transition duration-300">Small framework, big ears.</span>
                </p>

                <div class="mt-8 flex flex-wrap justify-center gap-4 lg:justify-start">
                    <a href="/docs/introduction" class="pop pop-hover rounded-full bg-brand-500 px-6 py-3 font-display text-base font-bold text-white">Get started &rarr;</a>
                    <a href="/docs/examples" class="pop pop-hover rounded-full bg-paper px-6 py-3 font-display text-base font-bold text-ink">Browse examples</a>
                </div>

                <div class="prose mx-auto mt-10 max-w-md lg:mx-0 [&_.code-block]:my-0">
                    <pre class="my-0! py-3! pt-4! text-left"><code class="language-bash">composer require notrab/dumbo</code></pre>
                </div>
            </div>

            <div class="relative mt-16 min-w-0 flex-1 lg:mt-0">
                <button type="button" data-elephant class="absolute -top-14 -right-1 z-10 select-none text-7xl motion-safe:animate-float sm:-top-16 sm:text-8xl" aria-label="Throw peanuts">🐘</button>
                <div class="absolute -top-10 right-20 z-10 hidden rotate-3 rounded-xl border-2 border-ink bg-white px-3 py-1.5 text-sm font-bold text-[#1c1917] sm:right-28 sm:block">
                    psst, click me
                    <span class="absolute top-1/2 -right-2 h-3 w-3 -translate-y-1/2 rotate-45 border-t-2 border-r-2 border-ink bg-white"></span>
                </div>

                <div class="pop overflow-hidden rounded-2xl bg-zinc-900">
                    <div class="flex items-center gap-2 border-b-2 border-ink bg-zinc-800 px-4 py-2.5">
                        <span class="h-3 w-3 rounded-full border border-black/30 bg-[#ff5f57]"></span>
                        <span class="h-3 w-3 rounded-full border border-black/30 bg-[#febc2e]"></span>
                        <span class="h-3 w-3 rounded-full border border-black/30 bg-[#28c840]"></span>
                        <span class="ml-2 font-mono text-xs text-zinc-400">index.php</span>
                    </div>
                    <pre class="overflow-x-auto p-5 font-mono text-[0.8125rem] leading-relaxed"><code class="language-php">&lt;?php

use Dumbo\Dumbo;

$app = new Dumbo();

$app-&gt;get('/', function ($context) {
    return $context-&gt;text('Hello Dumbo! 🐘');
});

$app-&gt;get('/peanuts/:count', function ($context) {
    return $context-&gt;json([
        'peanuts' =&gt; $context-&gt;req-&gt;param('count'),
    ]);
});

$app-&gt;run();</code></pre>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <h2 class="text-center font-display text-3xl font-extrabold tracking-tight text-ink sm:text-5xl">
            Everything you need, <span class="highlight">nothing you don't</span>
        </h2>
        <p class="mx-auto mt-4 max-w-xl text-center text-lg text-stone-600 dark:text-stone-400">
            Dumbo packs a surprising amount into a peanut-sized package.
        </p>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ([
                ["🚀", "Lightweight", "A handful of small classes on top of FastRoute and PSR-7. No magic, no bloat.", "bg-peanut-100", "-rotate-1"],
                ["🛣️", "Flexible routing", "Route params, nested apps and every HTTP method, with 405s handled for you.", "bg-brand-100", "rotate-1"],
                ["🧩", "Middleware", "Global or path-scoped middleware with a simple \$next(\$context) contract.", "bg-white", "-rotate-1"],
                ["🔒", "Secure by default", "CSRF, JWT, Basic and Bearer auth, body limits and signed cookies built in.", "bg-white", "rotate-1"],
                ["⚡", "Batteries included", "CORS, compression, HTTP caching, request IDs, logging and static files.", "bg-peanut-100", "-rotate-1"],
                ["🎨", "Bring your templates", "Plug in Twig, Blade, Latte or Smarty with one render closure.", "bg-brand-100", "rotate-1"],
            ] as [$icon, $heading, $text, $background, $tilt]): ?>
                <div class="pop pop-hover rounded-2xl p-6 text-[#1c1917] <?= $background ?> <?= $tilt ?> hover:rotate-0 dark:bg-stone-900 dark:text-stone-300">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl border-2 border-ink bg-white text-2xl dark:bg-stone-800"><?= $icon ?></div>
                    <h3 class="mt-4 font-display text-xl font-bold text-[#1c1917] dark:text-white"><?= e($heading) ?></h3>
                    <p class="mt-2 text-sm leading-relaxed opacity-80"><?= e($text) ?></p>
                </div>
            <?php endforeach ?>
        </div>
    </section>

    <section class="px-4 pb-20 sm:px-6">
        <div class="pop mx-auto flex max-w-4xl flex-col items-center gap-6 rounded-3xl bg-brand-500 px-6 py-12 text-center text-white sm:flex-row sm:px-12 sm:text-left">
            <div class="text-6xl motion-safe:animate-sway">🥜</div>
            <div class="flex-1">
                <h2 class="font-display text-3xl font-extrabold tracking-tight">Ready to stomp some routes?</h2>
                <p class="mt-2 text-brand-100">It takes about a minute to go from zero to Hello World.</p>
            </div>
            <a href="/docs/quickstart" class="pop pop-hover shrink-0 rounded-full bg-peanut-300 px-6 py-3 font-display font-bold text-[#1c1917]">Quickstart &rarr;</a>
        </div>
    </section>
</main>

<script>
    // Cycle through taglines
    const taglines = [
        "Small framework, big ears.",
        "Never forgets a route.",
        "Trunk-based routing, literally.",
        "Peanut-sized. Elephant-strong.",
        "Fewer dependencies than a circus.",
    ];
    const tagline = document.querySelector("[data-tagline]");
    let index = 0;

    if (!matchMedia("(prefers-reduced-motion: reduce)").matches) {
        setInterval(() => {
            tagline.classList.add("opacity-0", "-translate-y-2");
            setTimeout(() => {
                tagline.textContent = taglines[++index % taglines.length];
                tagline.classList.remove("opacity-0", "-translate-y-2");
            }, 300);
        }, 3000);
    }

    // The elephant throws peanuts when clicked
    document.querySelector("[data-elephant]").addEventListener("click", (event) => {
        const { left, top, width } = event.currentTarget.getBoundingClientRect();

        for (let i = 0; i < 12; i++) {
            const peanut = document.createElement("span");
            peanut.className = "peanut";
            peanut.textContent = "🥜";
            peanut.style.left = `${left + width / 3}px`;
            peanut.style.top = `${top + 20}px`;
            peanut.style.setProperty("--x", `${(Math.random() - 0.7) * 400}px`);
            peanut.style.setProperty("--y", `${Math.random() * 300 - 50}px`);
            peanut.style.setProperty("--r", `${Math.random() * 720 - 360}deg`);
            document.body.appendChild(peanut);
            peanut.addEventListener("animationend", () => peanut.remove());
        }
    });
</script>
