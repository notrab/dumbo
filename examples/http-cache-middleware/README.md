# Http Cache Example

This example demonstrates how to use the http cache middleware in Dumbo 

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

   ```bash
     curl -i http://localhost:8000/greet/dumbo
   ```
   
4. Access the route again and pass it the `If-None-Match` header from the previous response:

   ```bash
     curl -i -H "If-None-Match: $etag" http://localhost:8000/greet/dumbo
   ```



