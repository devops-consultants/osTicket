# Docker Configuration for osTicket

This directory contains Docker configuration files that align with the Kubernetes deployment strategy.

## Structure

- `Dockerfile`: Multi-stage build file for osTicket
  - `deployer` stage: Prepares osTicket files from source
  - `production` stage: PHP-FPM container for production use
  - `nginx` stage: Nginx container for serving static content

- `docker-compose.yml`: Local development configuration
  - Mirrors the Kubernetes deployment structure
  - Separates PHP-FPM and Nginx into different containers
  - Uses shared volumes for coordination
  - Sets resource limits similar to Kubernetes

## Container Structure

1. **PHP-FPM Container**
   - PHP 8.3 with FPM
   - Alpine-based for smaller footprint
   - Includes all required PHP extensions
   - Runs as non-root (www-data) user
   - Includes healthcheck for Kubernetes readiness probes

2. **Nginx Container**
   - Alpine-based
   - Runs as non-root (nginx) user
   - Configured as reverse proxy to PHP-FPM
   - Mounts the PHP application volume read-only

3. **MySQL Container**
   - Standard MySQL 8.0
   - Persistent data storage

## Usage

### Local Development

```bash
cd docker
docker-compose up -d
```

Access osTicket at http://localhost:8080

### Building for Kubernetes

#### Multi-Architecture Builds (AMD64 & ARM64)

For production Kubernetes deployments, build multi-architecture images to support both x86_64 (AMD64) and ARM64 platforms:

```bash
# Build and push multi-arch images
./build-for-k8s.sh --repo your-registry/osticket --tag v1.0.0 --platforms "linux/amd64,linux/arm64" --push
```

#### Testing Multi-Architecture Images

To test the multi-arch images before deploying to Kubernetes:

```bash
# Build multi-arch images without pushing
./build-for-k8s.sh --repo your-registry/osticket --tag test --platforms "linux/amd64,linux/arm64"

# Test the images
./test-docker.sh --build-multi-arch --check-arch
```

#### Single Architecture Build (Legacy)

For backward compatibility, you can still build single-architecture images:

```bash
docker build -t your-registry/osticket:latest -f docker/Dockerfile --target production .
docker build -t your-registry/osticket-nginx:latest -f docker/Dockerfile --target nginx .
docker push your-registry/osticket:latest
docker push your-registry/osticket-nginx:latest
```

Then deploy to Kubernetes using the manifests in the `kubernetes` directory.

## Resource Limits

The docker-compose file includes resource limits that mirror the Kubernetes deployment:

- PHP-FPM: 0.5 CPU, 256MB RAM
- Nginx: 0.2 CPU, 128MB RAM
- MySQL: 0.5 CPU, 512MB RAM

These can be adjusted as needed for your development environment.
