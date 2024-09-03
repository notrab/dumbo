# Docker FrankenPHP Example

This repository provides a basic example of setting up FrankenPHP in a Docker environment using Dumbo. The provided configuration is intended as a starting point for development and production deployments.

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
   docker-compose up --build app
   ```

3. Navigate to [localhost](https://localhost).

#### Production Environment

To build and start the Docker containers for production:

1. Build the Docker image:

   ```bash
   docker build --tag notrab/dumbo:docker-frankenphp-example .
   ```

2. Run the Docker container:

   ```bash
   docker run notrab/dumbo:docker-frankenphp-example
   ```

## Further Documentation

For more detailed information on FrankenPHP, refer to the official documentation:

- [FrankenPHP Documentation](https://frankenphp.dev/)
