# Turso Example

This example demonstrates how to use Turso HTTP with Dumbo to build a simple CRUD API.

## Running the Example

1. Install Turso CLI:

   ```bash
   brew install tursodatabase/tap/turso
   ```

2. Run Turso development server:

   ```bash
   turso dev -p 8001
   ```

3. Install dependencies:

   ```bash
   composer install
   ```

4. Start the server:

   ```bash
   composer start
   ```

5. Get all users:

   ```bash
   curl http://localhost:8000/users
   ```

6. Get a specific user (replace 1 with the desired user ID):

   ```bash
   curl http://localhost:8000/users/1
   ```

7. Create a new user:

   ```bash
   curl -X POST http://localhost:8000/users \
     -H "Content-Type: application/json" \
     -d '{"name": "John Doe", "email": "john@example.com"}'
   ```

8. Update a user (replace `1` with the desired user ID):

   ```bash
   curl -X PUT http://localhost:8000/users/1 \
     -H "Content-Type: application/json" \
     -d '{"name": "John Updated", "email": "john.updated@example.com"}'
   ```

9. Delete a user (replace `1` with the desired user ID):

   ```bash
   curl -X DELETE http://localhost:8000/users/1
   ```
