<?php $config = $site->config; ?>
<!DOCTYPE html>
<html lang="<?= e($config["locale"]) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($seo["title"]) ?></title>
    <meta name="description" content="<?= e($seo["description"]) ?>">
    <?php if ($seo["canonical"]): ?>
        <link rel="canonical" href="<?= e($seo["canonical"]) ?>">
        <meta property="og:url" content="<?= e($seo["canonical"]) ?>">
    <?php endif ?>
    <?php if ($seo["noindex"]): ?>
        <meta name="robots" content="noindex">
    <?php endif ?>
    <meta property="og:type" content="<?= e($seo["type"]) ?>">
    <meta property="og:site_name" content="<?= e($config["name"]) ?>">
    <meta property="og:title" content="<?= e($seo["title"]) ?>">
    <meta property="og:description" content="<?= e($seo["description"]) ?>">
    <meta property="og:locale" content="<?= e($config["locale"]) ?>">
    <meta name="twitter:card" content="<?= $seo["image"] ? "summary_large_image" : "summary" ?>">
    <?php if ($seo["image"]): ?>
        <meta property="og:image" content="<?= e($seo["image"]) ?>">
        <meta name="twitter:image" content="<?= e($seo["image"]) ?>">
    <?php endif ?>
    <?php if ($seo["twitter"]): ?>
        <meta name="twitter:site" content="<?= e($seo["twitter"]) ?>">
    <?php endif ?>
    <meta name="theme-color" content="<?= e($config["theme"]["primary"]) ?>">
    <?php if (!preg_match('/\.(svg|png|jpe?g|webp|gif)$/i', $config["logo"])): ?>
        <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22><?= e($config["logo"]) ?></text></svg>">
    <?php else: ?>
        <link rel="icon" href="<?= e($config["logo"]) ?>">
    <?php endif ?>
    <script>
        // Set the theme before paint to avoid a flash
        try {
            const theme = localStorage.getItem("theme");
            if (theme === "dark" || (!theme && matchMedia("(prefers-color-scheme: dark)").matches)) {
                document.documentElement.classList.add("dark");
            }
        } catch {}
    </script>
    <link rel="stylesheet" href="<?= e($site->asset("@fontsource-variable/bricolage-grotesque", "index.css")) ?>">
    <link rel="stylesheet" href="<?= e($site->asset("@fontsource-variable/instrument-sans", "index.css")) ?>">
    <link rel="stylesheet" href="<?= e($site->asset("@fontsource-variable/jetbrains-mono", "index.css")) ?>">
    <link rel="stylesheet" href="<?= e($site->file("/app.css")) ?>">
    <style>
        :root {
            --primary: <?= e($config["theme"]["primary"]) ?>;
            --accent: <?= e($config["theme"]["accent"]) ?>;
        }
    </style>
</head>
<body class="bg-paper font-sans text-stone-700 antialiased dark:text-stone-300">
    <header class="sticky top-0 z-40 border-b-2 border-ink bg-paper/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-[90rem] items-center gap-4 px-4 sm:px-6">
            <a href="/" class="group flex items-center gap-2 font-display text-2xl font-extrabold tracking-tight text-ink">
                <?= $site->view("logo", ["class" => "text-3xl group-hover:motion-safe:animate-wiggle"]) ?>
                <?= e($config["name"]) ?>
                <?php if ($config["badge"]): ?>
                    <span class="hidden -rotate-6 rounded-md border-2 border-ink bg-accent-300 px-1.5 py-0.5 font-sans text-[0.625rem] font-bold tracking-wide text-[#1c1917] uppercase md:inline-block"><?= e($config["badge"]) ?></span>
                <?php endif ?>
            </a>
            <nav class="ml-auto flex items-center gap-1 text-sm font-semibold">
                <?php foreach ($config["links"] as $i => $link): ?>
                    <a href="<?= e($link["href"]) ?>" class="<?= $i > 0 ? "hidden sm:block" : "" ?> rounded-full px-3 py-2 text-ink hover:bg-accent-200 dark:hover:bg-primary-500/30"><?= e($link["text"]) ?></a>
                <?php endforeach ?>
                <?php foreach ($config["social"] as $network => $url): ?>
                    <?php if ($network === "github"): ?>
                        <a href="<?= e($url) ?>" class="rounded-full p-2 text-ink hover:bg-accent-200 dark:hover:bg-primary-500/30" aria-label="GitHub">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .5a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2.2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1.1-.7.1-.7.1-.7 1.2.1 1.9 1.2 1.9 1.2 1.1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2-.1-.3-.5-1.5.1-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0C17.3 4.7 18.3 5 18.3 5c.7 1.7.2 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .5Z"/></svg>
                        </a>
                    <?php else: ?>
                        <a href="<?= e($url) ?>" class="hidden rounded-full px-3 py-2 text-ink hover:bg-accent-200 sm:block dark:hover:bg-primary-500/30"><?= e(match ($network) { "x" => "X", "bluesky" => "Bluesky", default => ucfirst($network) }) ?></a>
                    <?php endif ?>
                <?php endforeach ?>
                <button type="button" data-theme-toggle class="pop pop-hover ml-1 rounded-full bg-accent-300 p-1.5 text-[#1c1917] dark:bg-primary-500 dark:text-white" aria-label="Toggle dark mode">
                    <svg class="h-4 w-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                    <svg class="hidden h-4 w-4 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/></svg>
                </button>
            </nav>
        </div>
    </header>

    <?= $slot ?>

    <?php if ($config["footer"]["title"] || $config["footer"]["text"]): ?>
        <footer class="border-t-2 border-ink bg-accent-200 px-4 py-8 text-center text-sm text-[#1c1917] dark:bg-primary-950 dark:text-stone-300">
            <?php if ($config["footer"]["title"]): ?>
                <p class="font-display text-lg font-bold"><?= e($config["footer"]["title"]) ?></p>
            <?php endif ?>
            <?php if ($config["footer"]["text"]): ?>
                <p class="mt-1"><?= e($config["footer"]["text"]) ?></p>
            <?php endif ?>
            <p class="mt-3 text-xs opacity-75">Built with <a href="https://notrab.github.io/jumbo" class="font-semibold underline underline-offset-2 hover:opacity-100">Jumbo</a></p>
        </footer>
    <?php endif ?>

    <script>
        window.confetti = <?= json_encode($config["confetti"]) ?>;

        document.querySelector("[data-theme-toggle]").addEventListener("click", () => {
            const dark = document.documentElement.classList.toggle("dark");
            try { localStorage.setItem("theme", dark ? "dark" : "light"); } catch {}
        });

        const labels = { php: "PHP", bash: "Terminal", sh: "Terminal", json: "JSON", js: "JavaScript", javascript: "JavaScript", html: "HTML", css: "CSS", yaml: "YAML", dockerfile: "Dockerfile", nginx: "Nginx", apache: "Apache" };

        document.querySelectorAll(".prose pre:not([data-plain])").forEach((pre) => {
            const code = pre.querySelector("code");
            const language = [...(code?.classList ?? [])].find((c) => c.startsWith("language-"))?.slice(9);

            // Wrap the block so the label and button aren't clipped when it scrolls
            const block = document.createElement("div");
            block.className = "code-block";
            pre.replaceWith(block);
            block.appendChild(pre);

            if (labels[language]) {
                const label = document.createElement("span");
                label.className = "code-label";
                label.textContent = labels[language];
                block.appendChild(label);
            }

            const button = document.createElement("button");
            button.type = "button";
            button.className = "copy-button";
            button.textContent = "Copy";
            button.addEventListener("click", async () => {
                await navigator.clipboard.writeText(pre.innerText);
                button.textContent = `Copied! ${window.confetti}`;
                setTimeout(() => (button.textContent = "Copy"), 1500);
            });
            block.appendChild(button);
        });

        // Tabs: build a tab bar from each tab's title
        document.querySelectorAll("[data-tabs]").forEach((tabs, group) => {
            const panels = [...tabs.querySelectorAll(":scope > [data-tab]")];
            const bar = document.createElement("div");
            bar.className = "tab-bar";
            bar.setAttribute("role", "tablist");

            const select = (index) => panels.forEach((panel, i) => {
                panel.hidden = i !== index;
                bar.children[i].setAttribute("aria-selected", i === index);
            });

            panels.forEach((panel, i) => {
                const button = document.createElement("button");
                button.type = "button";
                button.setAttribute("role", "tab");
                button.textContent = panel.dataset.tab;
                button.addEventListener("click", () => select(i));
                bar.appendChild(button);
                panel.querySelector(".tab-title")?.remove();
            });

            tabs.prepend(bar);
            select(0);
        });
    </script>
</body>
</html>
