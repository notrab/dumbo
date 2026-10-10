<h1>Examples</h1>

<p>
    Every folder in the <a href="<?= e(GITHUB_URL) ?>/tree/main/examples">examples directory</a>
    is a small, runnable Dumbo app. Each one has its own <code>composer.json</code>, so you can
    <code>cd</code> in, run <code>composer install</code> and <code>composer start</code>.
</p>

<div class="not-prose mt-8">
    <label class="relative block">
        <span class="sr-only">Filter examples</span>
        <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
        <input type="search" data-filter placeholder="Filter <?= count($examples) ?> examples..."
               class="w-full rounded-lg border border-zinc-200 bg-white py-2.5 pr-3 pl-9 text-sm outline-none placeholder:text-zinc-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-900">
    </label>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <?php foreach ($examples as $example): ?>
            <a href="/docs/examples/<?= e($example["name"]) ?>" data-example="<?= e(strtolower($example["title"] . " " . $example["name"] . " " . strip_tags($example["description"]))) ?>"
               class="group rounded-lg border border-zinc-200 p-4 transition hover:border-brand-500 hover:bg-brand-50/50 dark:border-zinc-800 dark:hover:bg-brand-500/5">
                <span class="block font-semibold text-zinc-900 group-hover:text-brand-600 dark:text-white dark:group-hover:text-brand-300"><?= e($example["title"]) ?></span>
                <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400 [&_code]:rounded [&_code]:bg-zinc-100 [&_code]:px-1 [&_code]:text-xs dark:[&_code]:bg-zinc-800"><?= inline($example["description"]) ?></span>
            </a>
        <?php endforeach ?>
    </div>

    <p data-empty class="mt-6 hidden text-center text-sm text-zinc-500">No examples match your filter.</p>
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
