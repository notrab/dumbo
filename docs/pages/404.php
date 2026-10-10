<?php $this->layout("layout", ["seo" => $site->seo(["noindex" => true], "/404", "Page not found")]) ?>
<main class="mx-auto flex min-h-[70vh] max-w-xl flex-col items-center justify-center px-4 py-16 text-center">
    <div class="relative">
        <p class="text-8xl"><?= $site->view("logo", ["class" => "motion-safe:animate-sway"]) ?></p>
        <span class="absolute -top-2 -right-10 rotate-12 rounded-lg border-2 border-ink bg-accent-300 px-2 py-0.5 font-display text-lg font-extrabold text-[#1c1917]">404</span>
    </div>
    <h1 class="mt-8 font-display text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">
        This page <span class="highlight">wandered off</span>
    </h1>
    <p class="mt-4 text-lg text-stone-600 dark:text-stone-400">
        We looked everywhere. It might have moved, or it never existed.
    </p>
    <div class="mt-10 flex flex-wrap justify-center gap-4">
        <a href="/docs" class="pop pop-hover rounded-full bg-primary-500 px-6 py-3 font-display font-bold text-white">Back to the docs</a>
        <a href="/" class="pop pop-hover rounded-full bg-paper px-6 py-3 font-display font-bold text-ink">Take me home</a>
    </div>
</main>
