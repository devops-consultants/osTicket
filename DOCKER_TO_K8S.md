# Docker to Kubernetes Migration Guide

This guide explains how the Docker setup and Kubernetes deployment for osTicket are aligned to ensure smooth transitions from local development to production deployment.

## Architecture Comparison

### Docker Compose vs Kubernetes

| Component | Docker Compose | Kubernetes |
|-----------|---------------|------------|
| **PHP-FPM** | Container with shared volume | Deployment with shared emptyDir volume |
| **Nginx** | Container with shared volume | Deployment with shared emptyDir volume |
| **MySQL** | Single container | StatefulSet with PersistentVolumeClaim |
| **Config** | Environment variables | ConfigMaps and Secrets |
| **Networking** | Bridge network | Service resources and NetworkPolicy |
| **Storage** | Named volumes | PersistentVolumeClaims |
| **Health Checks** | Container healthcheck | Liveness and Readiness probes |

## Key Alignment Points

1. **Container Images**
   - Same base images used in both environments
   - Same build process via multi-stage Dockerfile
   - Multi-architecture support (amd64 and arm64) for mixed clusters
   - Same PHP extensions and configurations

2. **Resource Limits**
   - Docker Compose has resource limits that approximate Kubernetes limits
   - PHP-FPM: 0.5 CPU, 256MB RAM in both environments
   - Nginx: 0.2 CPU, 128MB RAM in both environments

3. **File Sharing**
   - Docker: Uses shared Docker volume between Nginx and PHP-FPM
   - Kubernetes: Uses emptyDir volume and init container to copy files

4. **Configuration**
   - Same ConfigMap values used in both environments
   - Same environment variables

5. **Health Checks**
   - Docker: Uses HEALTHCHECK directive
   - Kubernetes: Uses matching livenessProbe and readinessProbe

## Workflow

1. **Local Development**
   ```
   cd docker
   docker-compose up -d
   ```

2. **Testing Docker Configuration**
   ```
   ./docker/test-docker.sh
   ```

3. **Building Multi-Architecture Images for Kubernetes**
   ```
   ./docker/build-for-k8s.sh --repo yourregistry/osticket --tag v1.0.0 --platforms "linux/amd64,linux/arm64" --push
   ```

4. **Testing Multi-Architecture Images**
   ```
   ./docker/test-docker.sh --build-multi-arch --check-arch
   ```

5. **Deploying to Kubernetes**
   ```
   cd kubernetes
   ./deploy.sh --repo yourregistry/osticket --tag v1.0.0 --domain osticket.example.com
   ```

6. **Monitoring Kubernetes Deployment**
   ```
   ./kubernetes/monitor.sh
   ```

## Key Files for Reference

- `docker/docker-compose.yml` - Local development setup
- `docker/docker-compose.multi-arch.yml` - Multi-architecture image testing
- `docker/Dockerfile` - Multi-stage container build
- `docker/build-for-k8s.sh` - Multi-architecture build script
- `docker/test-docker.sh` - Docker environment testing script
- `kubernetes/php-fpm-deployment.yaml` - PHP-FPM deployment
- `kubernetes/nginx-deployment.yaml` - Nginx deployment
- `kubernetes/services.yaml` - Service definitions
- `kubernetes/php-configmap.yaml` - PHP configuration
- `kubernetes/nginx-configmap.yaml` - Nginx configuration

## Architecture Support

The build system now supports both AMD64 (x86_64) and ARM64 architectures, allowing deployment on:
- Traditional x86 server hardware
- AWS Graviton instances (ARM-based)
- Mixed Kubernetes clusters with both AMD64 and ARM64 nodes
- Development environments on Apple Silicon Macs
- Raspberry Pi and other ARM-based edge deployment options
