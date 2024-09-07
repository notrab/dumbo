# Body Limit Example

This example demonstrates how to use a body limit middleware in Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Access the route:

   Upload file less than 1MB (Success)

   ```bash
    curl -X POST http://localhost:8000/upload -F "file=@/path/to/your/less-than-1MB.file"
   ```

   Upload file larger than 1MB (Error)

   ```bash
    curl -X POST http://localhost:8000/upload -F "file=@/path/to/your/file-larger-than-1MB.file"
   ```
