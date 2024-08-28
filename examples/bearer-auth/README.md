# Bearer Auth Example

This example demonstrates how to use bearer authentication in Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Access the protected route:

   ```bash
     curl -H "Authorization: Bearer mysupersecret" http://localhost:8000
   ```
