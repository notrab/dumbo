# Cookies Example

This example demonstrates how to set and read cookies with Dumbo.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Test it out:

   ```bash
   curl -v -c cookies.txt -b cookies.txt http://localhost:8000/cookie
   ```

   ```bash
   curl -v -b cookies.txt http://localhost:8000/cookie?name=delicious_cookie
   ```

   ```bash
   curl http://localhost:8000/delete-cookie?name=delicious_cookie
   ```
