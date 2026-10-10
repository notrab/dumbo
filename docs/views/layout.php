<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <meta name="description" content="A lightweight, friendly PHP framework for HTTP, inspired by Hono.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐘</text></svg>">
    <script>
        // Set the theme before paint to avoid a flash
        try {
            const theme = localStorage.getItem("theme");
            if (theme === "dark" || (!theme && matchMedia("(prefers-color-scheme: dark)").matches)) {
                document.documentElement.classList.add("dark");
            }
        } catch {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Instrument+Sans:wght@400..700&family=JetBrains+Mono:wght@400;700&display=swap">
    <link rel="stylesheet" href="/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
</head>
<body class="bg-paper font-sans text-stone-700 antialiased dark:text-stone-300">
    <header class="sticky top-0 z-40 border-b-2 border-ink bg-paper/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-[90rem] items-center gap-4 px-4 sm:px-6">
            <a href="/" class="group flex items-center gap-2 font-display text-2xl font-extrabold tracking-tight text-ink">
                <span class="inline-block text-3xl group-hover:motion-safe:animate-wiggle">🐘</span>
                Dumbo
                <span class="hidden -rotate-6 rounded-md border-2 border-ink bg-peanut-300 px-1.5 py-0.5 font-sans text-[0.625rem] font-bold tracking-wide text-[#1c1917] uppercase md:inline-block">never forgets a route</span>
            </a>
            <nav class="ml-auto flex items-center gap-1 text-sm font-semibold">
                <a href="/docs" class="rounded-full px-3 py-2 text-ink hover:bg-peanut-200 dark:hover:bg-brand-500/30">Docs</a>
                <a href="/docs/examples" class="hidden rounded-full px-3 py-2 text-ink hover:bg-peanut-200 sm:block dark:hover:bg-brand-500/30">Examples</a>
                <a href="https://discord.gg/nAq2h9BfsU" class="hidden rounded-full px-3 py-2 text-ink hover:bg-peanut-200 sm:block dark:hover:bg-brand-500/30">Discord</a>
                <a href="https://github.com/notrab/dumbo" class="rounded-full p-2 text-ink hover:bg-peanut-200 dark:hover:bg-brand-500/30" aria-label="GitHub">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .5a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2.2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1.1-.7.1-.7.1-.7 1.2.1 1.9 1.2 1.9 1.2 1.1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2-.1-.3-.5-1.5.1-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0C17.3 4.7 18.3 5 18.3 5c.7 1.7.2 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .5Z"/></svg>
                </a>
                <button type="button" data-theme-toggle class="pop pop-hover ml-1 rounded-full bg-peanut-300 p-1.5 text-[#1c1917] dark:bg-brand-500 dark:text-white" aria-label="Toggle dark mode">
                    <svg class="h-4 w-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                    <svg class="hidden h-4 w-4 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/></svg>
                </button>
            </nav>
        </div>
    </header>

    <?= $body ?>

    <footer class="border-t-2 border-ink bg-peanut-200 py-8 text-center text-sm text-[#1c1917] dark:bg-brand-950 dark:text-stone-300">
        <p class="font-display text-lg font-bold">Made with 🥜 and PHP</p>
        <p class="mt-1">Released under the MIT License. Elephants never forget, but you might, so bookmark this.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/dockerfile.min.js"></script>
    <script>
        hljs.highlightAll();

        document.querySelector("[data-theme-toggle]").addEventListener("click", () => {
            const dark = document.documentElement.classList.toggle("dark");
            try { localStorage.setItem("theme", dark ? "dark" : "light"); } catch {}
        });

        const labels = { php: "PHP", bash: "Terminal", json: "JSON", dockerfile: "Dockerfile", nginx: "Nginx", apache: "Apache" };

        document.querySelectorAll(".prose pre").forEach((pre) => {
            const code = pre.querySelector("code");
            const language = [...code.classList].find((c) => c.startsWith("language-"))?.slice(9);

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
                await navigator.clipboard.writeText(code.innerText);
                button.textContent = "Copied! 🥜";
                setTimeout(() => (button.textContent = "Copy"), 1500);
            });
            block.appendChild(button);
        });
    </script>
</body>
</html>
