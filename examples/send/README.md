# Send Example

This example demonstrates how to return different types of responses with Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Access the different routes:

   ```bash
   # Get the SVG
   curl -i http://localhost:8000/logo > logo.svg

   # Get the JPEG
   curl -i http://localhost:8000/image > image.jpeg

   # Get the README
   curl -i http://localhost:8000/readme

   # Get the CSV
   curl -i http://localhost:8000/export > users.csv

   # Get the XML
   curl -i http://localhost:8000/feed
   ```
