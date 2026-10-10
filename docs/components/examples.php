<?php
/**
 * ::: examples
 * :::
 *
 * A filterable list of every folder in the repo's examples directory, with
 * descriptions from their READMEs. Specific to Dumbo's docs.
 */
$repo = "https://github.com/notrab/dumbo/tree/main/examples";
$words = [
    "cors" => "CORS", "docker" => "Docker", "fpm" => "FPM", "frankenphp" => "FrankenPHP",
    "html" => "HTML", "htmx" => "HTMX", "http" => "HTTP", "id" => "ID", "json" => "JSON",
    "jwt" => "JWT", "nginx" => "Nginx", "php" => "PHP", "txt" => "TXT", "blade" => "Blade",
    "latte" => "Latte", "smarty" => "Smarty", "turso" => "Turso", "twig" => "Twig",
];
$examples = [];

foreach (glob($site->root . "/../examples/*", GLOB_ONLYDIR) as $dir) {
    $name = basename($dir);
    $readme = is_file("$dir/README.md") ? file_get_contents("$dir/README.md") : "";

    // The first sentence of the first paragraph, with "This example
    // demonstrates how to use X" shortened to "Use X"
    $blocks = preg_split('/\n\s*\n/', trim($readme));
    $description = (string) current(array_filter($blocks, fn($b) => $b !== "" && !preg_match('/^\s*(#|```|[-*>]|\d+\.)/', $b)));
    $description = preg_replace('/\.\s+(?=[A-Z]).*$/s', ".", trim($description));
    $description = ucfirst(preg_replace('/^This (?:example|repository) (?:demonstrates|showcases|shows|provides) how to /i', "", $description));

    $examples[] = [
        "name" => $name,
        "title" => ucfirst(implode(" ", array_map(fn($w) => $words[$w] ?? $w, explode("-", $name)))),
        "description" => preg_replace('/`([^`]+)`/', '<code>$1</code>', e(preg_replace('/\s+/', " ", $description ?: "A Dumbo example."))),
    ];
}
?>
<div class="not-prose mt-8" data-examples>
    <label class="relative block">
        <span class="sr-only">Filter examples</span>
        <svg class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-stone-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
        <input type="search" data-filter placeholder="Filter <?= count($examples) ?> examples..."
               class="pop w-full rounded-full bg-paper py-3 pr-4 pl-10 text-sm text-ink outline-none placeholder:text-stone-400 focus:ring-4 focus:ring-accent-300 dark:focus:ring-primary-500/50">
    </label>

    <div class="mt-8 grid gap-5 sm:grid-cols-2">
        <?php foreach ($examples as $example): ?>
            <a href="<?= e("$repo/{$example["name"]}") ?>" data-example="<?= e(strtolower("{$example["title"]} {$example["name"]} " . strip_tags($example["description"]))) ?>"
               class="pop pop-hover group rounded-xl bg-paper p-4 hover:bg-accent-100 dark:hover:bg-primary-950">
                <span class="block font-display text-lg font-bold text-ink"><?= e($example["title"]) ?> <span class="text-sm opacity-40 transition group-hover:opacity-100">&nearr;</span></span>
                <span class="mt-1 block text-sm text-stone-600 dark:text-stone-400 [&_code]:rounded [&_code]:bg-primary-50 [&_code]:px-1 [&_code]:font-mono [&_code]:text-xs [&_code]:text-primary-700 dark:[&_code]:bg-primary-500/15 dark:[&_code]:text-primary-200"><?= $example["description"] ?></span>
            </a>
        <?php endforeach ?>
    </div>

    <p data-empty class="mt-10 hidden text-center text-stone-500">🙈 No examples match that. Try fewer peanuts?</p>
</div>

<script>
    document.querySelectorAll("[data-examples]").forEach((list) => {
        const filter = list.querySelector("[data-filter]");
        filter.addEventListener("input", () => {
            const query = filter.value.trim().toLowerCase();
            let shown = 0;
            list.querySelectorAll("[data-example]").forEach((card) => {
                const match = card.dataset.example.includes(query);
                card.classList.toggle("hidden", !match);
                shown += match;
            });
            list.querySelector("[data-empty]").classList.toggle("hidden", shown > 0);
        });
    });
</script>
