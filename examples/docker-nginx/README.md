# Docker Nginx Example

This repository provides a basic example of setting up Nginx in a Docker environment using Dumbo. The provided configuration is intended as a starting point for development and production deployments.

## Prerequisites

- Ensure that you have [Docker](https://www.docker.com/) installed on your system.

## Running the Example

### 1. Install Dependencies

Before building the Docker images, you need to install the project dependencies using Composer:

```bash
composer install
```

### 2. Build and Start Docker Containers

#### Development Environment

To build and start the Docker containers for development:

1. Build the Docker images:

   ```bash
   docker-compose -f docker-compose.yml build
   ```

2. Start the Docker containers:

   ```bash
   docker-compose up --build web
   ```

3. Navigate to [localhost](http://localhost:8080).

#### Production Environment

To build and start the Docker containers for production:

1. Build the Docker image:

   ```bash
   docker build --tag notrab/dumbo:docker-nginx-example .
   ```

2. Run the Docker container:

   ```bash
   docker run notrab/dumbo:docker-nginx-example
   ```
