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
    <link rel="stylesheet" href="/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
</head>
<body class="bg-white text-zinc-700 antialiased dark:bg-zinc-950 dark:text-zinc-300">
    <header class="sticky top-0 z-40 border-b border-zinc-200 bg-white/80 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/80">
        <div class="mx-auto flex h-16 max-w-[90rem] items-center gap-4 px-4 sm:px-6">
            <a href="/" class="flex items-center gap-2 text-lg font-bold text-zinc-900 dark:text-white">
                <span class="text-2xl">🐘</span> Dumbo
            </a>
            <nav class="ml-auto flex items-center gap-1 text-sm font-medium">
                <a href="/docs" class="rounded-md px-3 py-2 hover:text-brand-500">Docs</a>
                <a href="/docs/examples" class="hidden rounded-md px-3 py-2 hover:text-brand-500 sm:block">Examples</a>
                <a href="https://discord.gg/nAq2h9BfsU" class="hidden rounded-md px-3 py-2 hover:text-brand-500 sm:block">Discord</a>
                <a href="https://github.com/notrab/dumbo" class="rounded-md p-2 hover:text-brand-500" aria-label="GitHub">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .5a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2.2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1.1-.7.1-.7.1-.7 1.2.1 1.9 1.2 1.9 1.2 1.1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2-.1-.3-.5-1.5.1-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0C17.3 4.7 18.3 5 18.3 5c.7 1.7.2 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .5Z"/></svg>
                </a>
                <button type="button" data-theme-toggle class="rounded-md p-2 hover:text-brand-500" aria-label="Toggle dark mode">
                    <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/></svg>
                </button>
            </nav>
        </div>
    </header>

    <?= $body ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/dockerfile.min.js"></script>
    <script>
        hljs.highlightAll();

        document.querySelector("[data-theme-toggle]").addEventListener("click", () => {
            const dark = document.documentElement.classList.toggle("dark");
            try { localStorage.setItem("theme", dark ? "dark" : "light"); } catch {}
        });

        document.querySelectorAll(".prose pre").forEach((pre) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "copy-button";
            button.textContent = "Copy";
            button.addEventListener("click", async () => {
                await navigator.clipboard.writeText(pre.querySelector("code").innerText);
                button.textContent = "Copied!";
                setTimeout(() => (button.textContent = "Copy"), 1500);
            });
            pre.appendChild(button);
        });
    </script>
</body>
</html>
