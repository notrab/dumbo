<?php $this->layout("layout", ["title" => "Contact"]) ?>

<h1>🥜 Get in touch</h1>

<form method="post" class="card" novalidate>
    <?php if ($contact && !$contact->ok()): ?>
        <p class="alert">Check the highlighted fields and try again.</p>
    <?php endif ?>

    <label>
        Name
        <input name="name" value="<?= e($contact?->old("name")) ?>" autocomplete="name">
        <?php if ($error = $contact?->error("name")): ?><span class="error"><?= e($error) ?></span><?php endif ?>
    </label>

    <label>
        Email
        <input name="email" type="email" value="<?= e($contact?->old("email")) ?>" autocomplete="email">
        <?php if ($error = $contact?->error("email")): ?><span class="error"><?= e($error) ?></span><?php endif ?>
    </label>

    <label>
        Message
        <textarea name="message" rows="5"><?= e($contact?->old("message")) ?></textarea>
        <?php if ($error = $contact?->error("message")): ?><span class="error"><?= e($error) ?></span><?php endif ?>
    </label>

    <button>Send message</button>
</form>

<h2>The newsletter</h2>

<!-- Works without JavaScript, and the script below sends it without a reload -->
<form method="post" class="card inline" data-enhance>
    <input type="hidden" name="_action" value="subscribe">
    <input name="email" type="email" placeholder="you@example.com" value="<?= e($subscribe?->old("email")) ?>">
    <button>Subscribe</button>
    <p class="result <?= $subscribe && !$subscribe->ok() ? "error" : "" ?>" data-result><?= e($subscribe ? ($subscribe->ok() ? $subscribe->data["message"] : $subscribe->error("email")) : "") ?></p>
</form>

<script>
    document.querySelectorAll("form[data-enhance]").forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            // Asking for JSON gets {ok, data, errors} instead of the page
            const response = await fetch(location.href, {
                method: "POST",
                headers: { Accept: "application/json" },
                body: new FormData(form),
            });
            const { ok, data, errors } = await response.json();
            const result = form.querySelector("[data-result]");

            result.textContent = ok ? data.message : errors.email;
            result.classList.toggle("error", !ok);
        });
    });
</script>
