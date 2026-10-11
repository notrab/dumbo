---
description: Deploy a Dumbo app to bunny.net Magic Containers with Docker, and roll out every push with GitHub Actions.
---

# Magic Containers

[bunny.net Magic Containers](https://bunny.net/magic-containers/) runs your Docker image in regions around the world, behind bunny.net's CDN. A Dumbo app needs a `Dockerfile`, somewhere to push the image and a few minutes in the dashboard. After that, every push to `main` can deploy itself.

::: callout type="tip" title="Start from the example"
The [bunny-magic-containers example](https://github.com/notrab/dumbo/tree/main/examples/bunny-magic-containers) has the app, `Dockerfile` and GitHub Actions workflow from this page, ready to copy into a new repository.
:::

## 1. Add a Dockerfile

Put this next to your `composer.json`, with your front controller at `public/index.php`:

```dockerfile
# Magic Containers runs linux/amd64 images, so build for that platform:
#   docker build --platform linux/amd64 -t dumbo-app .
FROM dunglas/frankenphp:php8.3-alpine

# Serve plain HTTP on port 8080. The Magic Containers CDN endpoint takes
# HTTPS traffic and forwards it to this port.
ENV SERVER_NAME=:8080
ENV DUMBO_ENV=production

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions opcache \
    && echo "opcache.validate_timestamps=0" > "$PHP_INI_DIR/conf.d/zz-dumbo.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies first, so code changes don't reinstall them
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-progress --no-interaction --classmap-authoritative

COPY . .

# Boot the app once and keep it in memory between requests
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

EXPOSE 8080
```

And a `.dockerignore`, so your local `vendor/` doesn't end up in the image:

```text
.git
vendor
```

The image runs FrankenPHP in [worker mode](/docs/deployment#worker-mode). `$app->run()` notices and handles every request the worker receives, so `index.php` doesn't change. Try it before you deploy:

```bash
docker build --platform linux/amd64 -t dumbo-app .
docker run -p 8080:8080 dumbo-app
```

## 2. Push the image to a registry

Magic Containers pulls images from Docker Hub, GitHub Container Registry or a private registry you connect. This workflow builds the image on every push to `main` and pushes it to GitHub Container Registry. Save it as `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Magic Containers

on:
  push:
    branches: [main]
  workflow_dispatch:

permissions:
  contents: read
  packages: write

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      # GHCR image names must be lowercase
      - name: Name the image
        run: echo "IMAGE=ghcr.io/${GITHUB_REPOSITORY,,}" >> "$GITHUB_ENV"

      - uses: docker/setup-buildx-action@v3

      - uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - uses: docker/build-push-action@v6
        with:
          context: .
          platforms: linux/amd64
          push: true
          tags: |
            ${{ env.IMAGE }}:${{ github.sha }}
            ${{ env.IMAGE }}:latest

      # Roll the new image out to your app. Added in step 4.
      - uses: BunnyWay/actions/container-update-image@container-update-image_0.2.2
        with:
          app_id: ${{ vars.APP_ID }}
          api_key: ${{ secrets.BUNNYNET_API_KEY }}
          container: app
          image_tag: ${{ github.sha }}
```

Push it. The first run builds and pushes the image, then fails at the last step, as there's no app to update yet.

GitHub packages are private by default. Either make the package public, or add GitHub under **Magic Containers > Image Registries** in the bunny.net dashboard with a token that can read packages.

## 3. Create the app

::: steps
1. In the bunny.net dashboard, open **Magic Containers** and click **Add App**.
2. Pick your image. With `latest` pushed above, that tag is selected for you.
3. Name the container `app`, the name the workflow updates.
4. Click **+ CDN** to add an endpoint, and set the container port to `8080`. bunny.net serves your app over HTTPS and forwards requests to that port.
5. Add any environment variables your app reads. `DUMBO_ENV=production` is already set in the image.
6. Name the app, pick a region and click **Deploy**.
:::

Your app is live at the endpoint's address once the deploy finishes. Add more regions and autoscaling under **Regions and Scaling**.

## 4. Deploy on every push

Give the workflow access to your app. In your GitHub repository's **Settings > Secrets and variables > Actions**, add:

| Name | Type | Value |
| --- | --- | --- |
| `BUNNYNET_API_KEY` | Secret | Your bunny.net API key, from your account settings |
| `APP_ID` | Variable | Your app's ID, from its page in the dashboard |

Now every push to `main` builds a new image, tagged with the commit, and rolls it out.

## From the terminal

The [bunny.net CLI](https://www.npmjs.com/package/@bunny.net/cli) can do the same setup without the dashboard. Its Magic Containers commands are in preview, so expect them to change:

```bash
npm install -g @bunny.net/cli
bunny login
bunny apps deploy --dockerfile
```

The first deploy walks you through naming the app and choosing a registry and endpoint, then saves the answers to `bunny.jsonc` so later deploys need no questions.

## Good to know

- **Keep the app stateless.** Each region runs its own copy of your app, and worker mode keeps one in memory per worker, so store data in a database rather than in files or static properties.
- **Need files that survive deploys?** Add a persistent volume to the container and point your app at its mount path.
- **Health checks.** A cheap route like `$app->get("/health", fn($c) => $c->text("ok"))` gives uptime monitors something to call.
- **Images and assets.** Put a pull zone with [Bunny Optimizer](https://github.com/notrab/dumbo/tree/main/examples/bunny-optimizer) in front of your app to resize and compress images at the edge.
