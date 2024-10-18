# Environment Modes Example

This example demonstrates how to use different environment modes in Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server (defaults to development mode):

   ```bash
   composer start
   ```

3. Access the protected route:

   ```bash
   curl http://localhost:8000
   curl http://localhost:8000/error
   ```

4. Try running in production mode:

   ```bash
   DUMBO_ENV=production php -S localhost:8000
   ```

5. Access the same routes and notice the differences:

   ```bash
   curl http://localhost:8000
   curl http://localhost:8000/error
   ```

Note: If no environment is specified, Dumbo will default to development mode.
