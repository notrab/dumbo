# Compress Example

This example demonstrates how to compress responses using gzip or deflate in Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Make a request

   ```bash
    curl -H "Accept-Encoding: gzip" --compressed -i http://localhost:8000
   ```
