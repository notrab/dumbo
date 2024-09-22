# JSON Example

This example demonstrates how to return JSON with Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Authors API:

   a. Create a new author:

   ```bash
   curl -X POST http://localhost:8000/authors \
   -H "Content-Type: application/json" \
   -d '{"name": "John Doe", "email": "john@example.com"}'
   ```

   b. Get all authors:

   ```bash
   curl http://localhost:8000/authors
   ```

   c. Get a specific author (replace {id} with an actual author ID):

   ```bash
   curl http://localhost:8000/authors/{id}
   ```

4. Posts API:

   a. Create a new post:

   ```bash
   curl -X POST http://localhost:8000/posts \
   -H "Content-Type: application/json" \
   -d '{"title": "My First Post", "content": "This is the content of my first post.", "author_id": "{author_id}"}'
   ```

   b. Get all posts:

   ```bash
   curl http://localhost:8000/posts
   ```

   c. Get a specific post (replace {id} with an actual post ID):

   ```bash
   curl http://localhost:8000/posts/{id}
   ```

5. Comments API:

   a. Create a new comment:

   ```bash
   curl -X POST http://localhost:8000/comments \
   -H "Content-Type: application/json" \
   -d '{"content": "Great post!", "post_id": "{post_id}", "author_id": "{author_id}"}'
   ```

   b. Get all comments:

   ```bash
   curl http://localhost:8000/comments
   ```

   c. Get a specific comment (replace {id} with an actual comment ID):

   ```bash
   curl http://localhost:8000/comments/{id}
   ```

   d. Get all comments for a specific post (replace {post_id} with an actual post ID):

   ```bash
   curl http://localhost:8000/posts/{post_id}/comments
   ```
