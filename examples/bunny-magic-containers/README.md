# bunny.net Magic Containers Example

This example shows how to deploy a Dumbo app to bunny.net Magic Containers, which runs your Docker image in regions around the world. It runs on FrankenPHP in worker mode, so the app boots once and stays in memory.

## Running the Example

1. Install dependencies and start the PHP server on [http://localhost:8000](http://localhost:8000):

   ```bash
   composer install
   composer start
   ```

2. Or run it in Docker on [http://localhost:8080](http://localhost:8080), the way Magic Containers will:

   ```bash
   docker build --platform linux/amd64 -t dumbo-app .
   docker run -p 8080:8080 dumbo-app
   ```

   The JSON says `"worker": true` when it runs in FrankenPHP's worker mode.

## Deploying

This folder is meant to be the root of its own repository. Copy it into a new GitHub repository, then:

1. **Push an image.** Push to `main` and the workflow in `.github/workflows/deploy.yml` builds the image and pushes it to `ghcr.io/<you>/<repo>`. Its first run fails at the last step, as there's no app to update yet.
2. **Let bunny.net pull it.** GitHub packages are private by default. Either make the package public, or add GitHub under **Magic Containers > Image Registries** in the bunny.net dashboard with a token that can read packages.
3. **Create the app.** In **Magic Containers**, click **Add App**, pick the image, and name the container `app`.
4. **Add an endpoint.** Click **+ CDN** and set the container port to `8080`. bunny.net serves your app over HTTPS and forwards requests to that port.
5. **Set the environment.** `DUMBO_ENV=production` is already set in the image, so there's nothing required. Add your own variables here.
6. **Deploy.** Pick a region and click **Deploy**. Add more regions and autoscaling later under **Regions and Scaling**.
7. **Deploy on every push.** Add a repository secret `BUNNYNET_API_KEY` with your bunny.net API key and a variable `APP_ID` with the app's ID. From now on, every push to `main` builds a new image and rolls it out.

## What it shows

- A `Dockerfile` that serves plain HTTP on port 8080, as Magic Containers' CDN endpoint expects, with OPcache on and timestamp checks off
- FrankenPHP worker mode with `FRANKENPHP_CONFIG`, which `$app->run()` picks up with no code changes
- Building for `linux/amd64`, the only platform Magic Containers runs
- A `/health` route for uptime checks
- A GitHub Actions workflow that builds, pushes and rolls out with [`BunnyWay/actions/container-update-image`](https://github.com/BunnyWay/actions)

The full walkthrough is in the [Magic Containers guide](https://notrab.github.io/dumbo/docs/magic-containers).
