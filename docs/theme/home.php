<?php $this->layout("layout") ?>
<?php
$hero = $page["meta"]["hero"] ?? [];
$features = $page["meta"]["features"] ?? [];
$cta = $page["meta"]["cta"] ?? null;
$taglines = (array) ($hero["tagline"] ?? []);
$tints = ["bg-accent-100", "bg-primary-100", "bg-white"];

// *Words in asterisks* get a marker-pen highlight
$highlight = fn(string $text) => preg_replace('/\*(.+?)\*/', '<span class="highlight">$1</span>', e($text));

// Code is highlighted on the server like fenced code in Markdown, and escaped
$highlighter = new \Tempest\Highlight\Highlighter();
$code = fn(string $source, string $language) => $highlighter->parse(rtrim($source), $language);
?>
<main class="overflow-hidden">
    <section class="relative border-b-2 border-ink bg-[radial-gradient(var(--color-ink)_1px,transparent_1px)] [background-size:22px_22px]">
        <div class="absolute inset-0 bg-linear-to-b from-paper/60 via-paper/85 to-paper"></div>

        <div class="relative mx-auto max-w-6xl px-4 pt-14 pb-16 sm:px-6 sm:pt-20 lg:flex lg:items-center lg:gap-14 lg:pt-24 lg:pb-24">
            <div class="flex-1 text-center lg:text-left">
                <?php if (!empty($hero["badges"])): ?>
                    <div class="flex flex-wrap justify-center gap-2 lg:justify-start">
                        <?php foreach ($hero["badges"] as $i => $badge): ?>
                            <span class="<?= ["-rotate-3 bg-accent-300", "rotate-2 bg-primary-200", "-rotate-1 bg-white"][$i % 3] ?> rounded-full border-2 border-ink px-3 py-1 text-xs font-bold text-[#1c1917]"><?= e($badge) ?></span>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <h1 class="mt-6 font-display text-6xl font-extrabold tracking-tight text-ink sm:text-8xl">
                    <span class="highlight"><?= e($hero["name"] ?? $site->config["name"]) ?></span>
                </h1>
                <?php if (!empty($hero["text"])): ?>
                    <p class="mt-4 font-display text-2xl font-bold tracking-tight text-ink sm:text-4xl"><?= e($hero["text"]) ?></p>
                <?php endif ?>
                <?php if ($taglines): ?>
                    <p class="mt-4 min-h-7 text-lg text-stone-600 dark:text-stone-400">
                        <span data-tagline data-taglines="<?= e(json_encode($taglines)) ?>" class="inline-block transition duration-300"><?= e($taglines[0]) ?></span>
                    </p>
                <?php endif ?>

                <?php if (!empty($hero["actions"])): ?>
                    <div class="mt-8 flex flex-wrap justify-center gap-4 lg:justify-start">
                        <?php foreach ($hero["actions"] as $i => $action): ?>
                            <a href="<?= e($action["href"]) ?>" class="pop pop-hover rounded-full px-6 py-3 font-display text-base font-bold <?= ($action["style"] ?? ($i === 0 ? "primary" : "secondary")) === "primary" ? "bg-primary-500 text-white" : "bg-paper text-ink" ?>"><?= e($action["text"]) ?></a>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>

                <?php if (!empty($hero["install"])): ?>
                    <div class="prose mx-auto mt-10 max-w-md lg:mx-0 [&_.code-block]:my-0">
                        <pre class="my-0! py-3! pt-4! text-left"><code class="language-bash"><?= $code($hero["install"], "bash") ?></code></pre>
                    </div>
                <?php endif ?>
            </div>

            <?php if (!empty($hero["code"])): ?>
                <div class="relative mt-16 min-w-0 flex-1 lg:mt-0">
                    <?php if (!empty($hero["mascot"])): ?>
                        <button type="button" data-mascot class="absolute -top-14 -right-1 z-10 select-none text-7xl motion-safe:animate-float sm:-top-16 sm:text-8xl" aria-label="Throw confetti"><?= e($hero["mascot"]) ?></button>
                        <div class="absolute -top-10 right-20 z-10 hidden rotate-3 rounded-xl border-2 border-ink bg-white px-3 py-1.5 text-sm font-bold text-[#1c1917] sm:right-28 sm:block">
                            psst, click me
                            <span class="absolute top-1/2 -right-2 h-3 w-3 -translate-y-1/2 rotate-45 border-t-2 border-r-2 border-ink bg-white"></span>
                        </div>
                    <?php endif ?>

                    <div class="pop overflow-hidden rounded-2xl bg-code">
                        <div class="flex items-center gap-2 border-b-2 border-ink bg-code-bar px-4 py-2.5">
                            <span class="h-3 w-3 rounded-full border border-black/30 bg-[#ff5f57]"></span>
                            <span class="h-3 w-3 rounded-full border border-black/30 bg-[#febc2e]"></span>
                            <span class="h-3 w-3 rounded-full border border-black/30 bg-[#28c840]"></span>
                            <?php if (!empty($hero["code"]["file"])): ?>
                                <span class="ml-2 font-mono text-xs text-code-comment"><?= e($hero["code"]["file"]) ?></span>
                            <?php endif ?>
                        </div>
                        <pre class="overflow-x-auto p-5 font-mono text-[0.8125rem] leading-relaxed text-code-text"><code class="language-<?= e($hero["code"]["language"] ?? "plaintext") ?>"><?= $code($hero["code"]["source"] ?? "", $hero["code"]["language"] ?? "plaintext") ?></code></pre>
                    </div>
                </div>
            <?php endif ?>
        </div>
    </section>

    <?php if ($features): ?>
        <section class="mx-auto max-w-5xl px-4 py-20 sm:px-6">
            <?php if (!empty($page["meta"]["features_title"])): ?>
                <h2 class="text-center font-display text-3xl font-extrabold tracking-tight text-ink sm:text-5xl"><?= $highlight($page["meta"]["features_title"]) ?></h2>
            <?php endif ?>
            <?php if (!empty($page["meta"]["features_text"])): ?>
                <p class="mx-auto mt-4 max-w-xl text-center text-lg text-stone-600 dark:text-stone-400"><?= e($page["meta"]["features_text"]) ?></p>
            <?php endif ?>

            <div class="mt-14 grid gap-8 md:grid-cols-2">
                <?php foreach ($features as $i => $feature): ?>
                    <div class="pop pop-hover flex min-w-0 flex-col rounded-2xl p-6 text-[#1c1917] <?= $tints[$i % 3] ?> <?= $i % 2 ? "rotate-1" : "-rotate-1" ?> hover:rotate-0 dark:bg-stone-900 dark:text-stone-300">
                        <?php if (!empty($feature["icon"])): ?>
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl border-2 border-ink bg-white text-2xl dark:bg-stone-800"><?= e($feature["icon"]) ?></div>
                        <?php endif ?>
                        <h3 class="font-display text-xl font-bold text-[#1c1917] dark:text-white"><?= e($feature["title"]) ?></h3>
                        <p class="mt-2 text-sm leading-relaxed opacity-80"><?= e($feature["details"] ?? "") ?></p>
                        <?php if (!empty($feature["code"])): ?>
                            <pre class="mt-5 overflow-x-auto rounded-xl border-2 border-ink bg-code p-4 font-mono text-xs leading-relaxed text-code-text"><code class="language-<?= e($feature["language"] ?? "php") ?>"><?= $code($feature["code"], $feature["language"] ?? "php") ?></code></pre>
                        <?php endif ?>
                        <?php if (!empty($feature["href"])): ?>
                            <a href="<?= e($feature["href"]) ?>" class="mt-auto inline-flex items-center gap-1 self-start pt-5 text-sm font-bold text-primary-700 underline decoration-accent-400 decoration-2 underline-offset-4 hover:decoration-primary-500 dark:text-primary-300"><?= e($feature["link"] ?? "Learn more") ?> &rarr;</a>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>

    <?php if (trim(strip_tags($page["html"])) !== ""): ?>
        <section class="prose prose-stone mx-auto max-w-3xl px-4 pb-20 sm:px-6 dark:prose-invert prose-headings:text-ink prose-h2:text-center prose-h2:text-3xl prose-h2:font-extrabold sm:prose-h2:text-5xl prose-p:text-center prose-p:text-lg">
            <?= $page["html"] ?>
        </section>
    <?php endif ?>

    <?php if ($cta): ?>
        <section class="px-4 pb-20 sm:px-6">
            <div class="pop mx-auto flex max-w-4xl flex-col items-center gap-6 rounded-3xl bg-primary-500 px-6 py-12 text-center text-white sm:flex-row sm:px-12 sm:text-left">
                <?php if (!empty($cta["icon"])): ?>
                    <div class="text-6xl motion-safe:animate-sway"><?= e($cta["icon"]) ?></div>
                <?php endif ?>
                <div class="flex-1">
                    <h2 class="font-display text-3xl font-extrabold tracking-tight"><?= e($cta["title"]) ?></h2>
                    <?php if (!empty($cta["text"])): ?>
                        <p class="mt-2 text-primary-100"><?= e($cta["text"]) ?></p>
                    <?php endif ?>
                </div>
                <?php if (!empty($cta["action"])): ?>
                    <a href="<?= e($cta["action"]["href"]) ?>" class="pop pop-hover shrink-0 rounded-full bg-accent-300 px-6 py-3 font-display font-bold text-[#1c1917]"><?= e($cta["action"]["text"]) ?></a>
                <?php endif ?>
            </div>
        </section>
    <?php endif ?>
</main>

<script>
    // Cycle through taglines
    const tagline = document.querySelector("[data-tagline]");
    const taglines = tagline ? JSON.parse(tagline.dataset.taglines) : [];
    let index = 0;

    if (taglines.length > 1 && !matchMedia("(prefers-reduced-motion: reduce)").matches) {
        setInterval(() => {
            tagline.classList.add("opacity-0", "-translate-y-2");
            setTimeout(() => {
                tagline.textContent = taglines[++index % taglines.length];
                tagline.classList.remove("opacity-0", "-translate-y-2");
            }, 300);
        }, 3000);
    }

    // The mascot throws confetti when clicked
    document.querySelector("[data-mascot]")?.addEventListener("click", (event) => {
        const { left, top, width } = event.currentTarget.getBoundingClientRect();

        for (let i = 0; i < 12; i++) {
            const piece = document.createElement("span");
            piece.className = "confetti";
            piece.textContent = window.confetti;
            piece.style.left = `${left + width / 3}px`;
            piece.style.top = `${top + 20}px`;
            piece.style.setProperty("--x", `${(Math.random() - 0.7) * 400}px`);
            piece.style.setProperty("--y", `${Math.random() * 300 - 50}px`);
            piece.style.setProperty("--r", `${Math.random() * 720 - 360}deg`);
            document.body.appendChild(piece);
            piece.addEventListener("animationend", () => piece.remove());
        }
    });
</script>
