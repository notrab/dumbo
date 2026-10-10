<h1>Examples</h1>

<p class="lead">Forty-odd little apps, each doing one thing well. Steal freely. 🐘</p>

<p>
    Every folder in the <a href="<?= e(GITHUB_URL) ?>/tree/main/examples">examples directory</a>
    is a small, runnable Dumbo app. Each one has its own <code>composer.json</code>, so you can
    <code>cd</code> in, run <code>composer install</code> and <code>composer start</code>.
</p>

<div class="not-prose mt-8">
    <label class="relative block">
        <span class="sr-only">Filter examples</span>
        <svg class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
        <input type="search" data-filter placeholder="Filter <?= count($examples) ?> examples..."
               class="pop w-full rounded-full bg-paper py-3 pr-4 pl-10 text-sm text-ink outline-none placeholder:text-stone-400 focus:ring-4 focus:ring-peanut-300 dark:focus:ring-brand-500/50">
    </label>

    <div class="mt-8 grid gap-5 sm:grid-cols-2">
        <?php foreach ($examples as $example): ?>
            <a href="/docs/examples/<?= e($example["name"]) ?>" data-example="<?= e(strtolower($example["title"] . " " . $example["name"] . " " . strip_tags($example["description"]))) ?>"
               class="pop pop-hover group rounded-xl bg-paper p-4 hover:bg-peanut-100 dark:hover:bg-brand-950">
                <span class="block font-display text-lg font-bold text-ink"><?= e($example["title"]) ?></span>
                <span class="mt-1 block text-sm text-stone-600 dark:text-stone-400 [&_code]:rounded [&_code]:bg-brand-50 [&_code]:px-1 [&_code]:font-mono [&_code]:text-xs [&_code]:text-brand-700 dark:[&_code]:bg-brand-500/15 dark:[&_code]:text-brand-200"><?= inline($example["description"]) ?></span>
            </a>
        <?php endforeach ?>
    </div>

    <p data-empty class="mt-10 hidden text-center text-stone-500">🙈 No examples match that. Try fewer peanuts?</p>
</div>

<script>
    const filter = document.querySelector("[data-filter]");
    filter.addEventListener("input", () => {
        const query = filter.value.trim().toLowerCase();
        let shown = 0;
        document.querySelectorAll("[data-example]").forEach((card) => {
            const match = card.dataset.example.includes(query);
            card.classList.toggle("hidden", !match);
            shown += match;
        });
        document.querySelector("[data-empty]").classList.toggle("hidden", shown > 0);
    });
</script>
