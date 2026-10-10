# Database (PDO) Example

This example demonstrates how to share a PDO connection with your routes using context middleware, and build a simple CRUD API with prepared statements.

It uses a local SQLite file by default. To use MySQL or Postgres, set `DATABASE_DSN`, `DATABASE_USER` and `DATABASE_PASSWORD` and create a `users` table with `id`, `name` and a unique `email` column.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Create a new user:

   ```bash
   curl -X POST http://localhost:8000/users \
     -H "Content-Type: application/json" \
     -d '{"name": "John Doe", "email": "john@example.com"}'
   ```

4. Get all users:

   ```bash
   curl http://localhost:8000/users
   ```

5. Get a specific user (replace `1` with the desired user ID):

   ```bash
   curl http://localhost:8000/users/1
   ```

6. Update a user (replace `1` with the desired user ID):

   ```bash
   curl -X PUT http://localhost:8000/users/1 \
     -H "Content-Type: application/json" \
     -d '{"name": "John Updated"}'
   ```

7. Delete a user (replace `1` with the desired user ID):

   ```bash
   curl -X DELETE http://localhost:8000/users/1
   ```
