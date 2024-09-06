# JSON Example

This example demonstrates how to enable CORS in Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Make a request with CORS middleware enabled:

   ```bash
    curl -H "Origin: http://example.com" \
     -H "Accept: application/json" \
     -X GET \
     http://localhost:8000/ \
     -v
   ```
