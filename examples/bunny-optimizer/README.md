# Bunny Optimizer Example

This example shows how to serve responsive images from a Dumbo app through Bunny Optimizer, which resizes, crops and compresses them at bunny.net's edge. Each image is a single original in `public/images`, and the page asks for the sizes it needs in the query string.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Open [http://localhost:8000](http://localhost:8000) and look at the image URLs.

Locally there's no Optimizer, so every size is the full original. Put a pull zone in front, below, and the same URLs return resized, compressed copies.

## Setting up Bunny Optimizer

1. **Deploy the app** anywhere with a public URL. The [Magic Containers example](../bunny-magic-containers) is one way.
2. **Create a pull zone** in the bunny.net dashboard, with your app's URL as its origin.
3. **Turn on Bunny Optimizer** for the pull zone, then turn on the **Dynamic Image API** in its Optimizer settings. Turn on WebP too, so browsers that support it get WebP copies.
4. **Load images through the pull zone.** Set `CDN_URL` to its address:

   ```bash
   CDN_URL=https://your-zone.b-cdn.net
   ```

   Pages keep coming from your app, and images now come from the pull zone.

## What it shows

- An `image()` helper that adds Optimizer parameters, like `?width=640&quality=80`
- A `srcset()` helper, so browsers download the smallest image that fills the space
- Square thumbnails cropped from wide photos with `aspect_ratio=1:1`
- A version in each image URL, taken from the file's contents, so images can be cached for a year with `Cache-Control: immutable` and still update when the file changes
- `CDN_URL` to load images from a pull zone while pages come from the app

The parameters are in bunny.net's [dynamic images docs](https://docs.bunny.net/optimizer/dynamic-images).
