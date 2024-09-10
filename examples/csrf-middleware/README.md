# Middleware Example

This example demonstrates how middleware executes in Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Try valid origin:

    ```bash
    curl -X POST http://localhost:8000/api/greet -H "Origin: http://localhost:8000" -H "Content-Type: application/x-www-form-urlencoded"
    ```

4. Try invalid origin:

    ```bash
    curl -X POST http://localhost:8000/api/greet -d "name=Dumbo" -H "Origin: http://example.com" -H "Content-Type: application/x-www-form-urlencoded"
    ```