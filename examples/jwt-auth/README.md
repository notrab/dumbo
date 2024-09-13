# JWT Auth Example

This example demonstrates how to use JWT authentication in Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Access the public route:

   ```bash
    curl http://localhost:8000
   ```

4. Try to access the protected route (should fail):

   ```bash
    curl http://localhost:8000/protected
   ```

5. Login:

   ```bash
   curl -X POST -H "Content-Type: application/json" -d '{"username":"user1","password":"password1"}' http://localhost:8000/login -c cookies.txt
   ```

6. Access the protected route with the JWT cookie:

   ```bash
   curl http://localhost:8000/protected -b cookies.txt
   ```

7. Logout:

   ```bash
   curl -X POST http://localhost:8000/logout -b cookies.txt
   ```
