# Route Params Example

This example demonstrates how to use route params with Dumbo.

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
    # Single parameter
    curl http://localhost:8000/hello/John

    # Multiple parameters
    curl http://localhost:8000/users/123/posts/456

    # Optional parameter (query string)
    curl http://localhost:8000/?name=Alice
    curl http://localhost:8000/
   ```
