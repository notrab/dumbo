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

3. To test the cached route, use the curl command to send a GET request to http://localhost:8000/cached/greet. This
   route has the CacheMiddleware applied to it, so it should return cache control headers. Here's the command:

   ```bash
     curl -i http://localhost:8000/cached/greet
   ```

   Look for the ETag and Cache-Control headers in the response. The ETag header is a unique identifier for the version
   of
   the resource, and the Cache-Control header indicates the caching policy


4. To test the cache functionality, you can send the same request again, but this time include the If-None-Match header
   with the ETag value from the previous response. If the resource hasn't changed, the server should return a 304 Not
   Modified status. Here's the command:

   ```bash
     curl -i -H "If-None-Match: $etag" http://localhost:8000/cached/greet
   ```
5. To test the uncached route, send a GET request to http://localhost:8000/uncached/greet. This route does not have the
   CacheMiddleware applied to it, so it should not return cache control headers. Here's the command:

   ```bash
     curl -i http://localhost:8000/uncached/greet
   ```


