<?php
/**
 * ::: prompt file="agent-setup.md" title="Set up Dumbo"
 * :::
 *
 * Shows a file from public/ with a copy button, for prompts people paste into
 * a coding agent. The file is also served at its own URL for agents to fetch.
 */
$file = basename($attributes["file"] ?? "");
$path = "$site->root/public/$file";
$text = $file !== "" && is_file($path) ? file_get_contents($path) : "Missing public/$file";
?>
<div class="not-prose pop my-8 overflow-hidden rounded-2xl bg-zinc-900" data-prompt>
    <div class="flex flex-wrap items-center gap-3 border-b-2 border-ink bg-accent-300 px-4 py-3 text-[#1c1917]">
        <span class="font-display text-base font-bold"><?= e($attributes["title"] ?? "Prompt") ?></span>
        <a href="/<?= e($file) ?>" class="ml-auto text-sm font-semibold underline decoration-2 underline-offset-4">View raw</a>
        <button type="button" data-copy-prompt class="pop pop-hover rounded-full bg-primary-500 px-4 py-1.5 font-display text-sm font-bold text-white">Copy prompt</button>
    </div>
    <div class="relative">
        <pre data-plain class="max-h-80 overflow-auto p-5 font-mono text-[0.8125rem] leading-relaxed whitespace-pre-wrap text-zinc-300"><?= e($text) ?></pre>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-linear-to-t from-zinc-900"></div>
    </div>
</div>

<script>
    document.querySelectorAll("[data-prompt]").forEach((prompt) => {
        const button = prompt.querySelector("[data-copy-prompt]");
        button.addEventListener("click", async () => {
            await navigator.clipboard.writeText(prompt.querySelector("pre").textContent);
            button.textContent = `Copied! ${window.confetti ?? ""}`;
            setTimeout(() => (button.textContent = "Copy prompt"), 1800);
        });
    });
</script>
