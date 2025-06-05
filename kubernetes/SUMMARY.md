# OSTicket Kubernetes Deployment

## Multi-Architecture Support

This deployment fully supports both AMD64 (x86_64) and ARM64 architectures. Container images are built as multi-architecture images, enabling deployment on:

- Traditional x86-64 servers
- ARM-based servers (AWS Graviton, Ampere Altra, etc.)
- Mixed architecture Kubernetes clusters
- Edge computing devices (Raspberry Pi, etc.)

## Files Created

### Core Kubernetes Manifests
- `namespace.yaml` - Creates osticket namespace
- `php-configmap.yaml` - ConfigMap for PHP and PHP-FPM configuration
- `nginx-configmap.yaml` - ConfigMap for Nginx configuration
- `osticket-env-configmap.yaml` - Environment variables for OSTicket
- `osticket-secrets.yaml` - Secrets for database credentials
- `persistent-volumes.yaml` - PVCs for plugins and translations
- `php-fpm-deployment.yaml` - PHP-FPM deployment with resource limits
- `nginx-deployment.yaml` - Nginx deployment as reverse proxy
- `services.yaml` - ClusterIP services for internal communication
- `ingress.yaml` - Ingress resource for external access
- `horizontal-pod-autoscaler.yaml` - Auto-scaling based on CPU/memory
- `mysql.yaml` - StatefulSet for MySQL database

### Security and Reliability
- `network-policy.yaml` - Network security policies
- `pod-disruption-budget.yaml` - Ensures availability during node maintenance
- `resource-quota.yaml` - Limits resources for the namespace

### Scripts and Documentation
- `deploy.sh` - Deployment script with customization options
- `monitor.sh` - Monitoring script for deployment status
- `README.md` - Detailed deployment instructions
- `SCALING.md` - Guide for scaling the deployment

### CI/CD Integration
- `.github/workflows/ci-cd.yml` - GitHub Actions workflow for CI/CD

## Security Features
- Non-root containers
- Minimal container capabilities
- Network policies for traffic segmentation
- Proper secret management
- Resource limits to prevent DoS

## Scalability Features
- Horizontal Pod Autoscalers
- Separate services for better scaling
- Resource optimization
- Persistent storage for critical data

## Availability Features
- Pod Disruption Budgets
- Multi-replica deployments
- Health checks and readiness probes
- Rolling update strategy

## Next Steps
1. Build and push multi-architecture Docker images:
   ```
   ./docker/build-for-k8s.sh --repo your-registry/osticket --tag v1.0.0 --platforms "linux/amd64,linux/arm64" --push
   ```

2. Test the multi-arch images (optional):
   ```
   ./docker/test-multi-arch.sh --repo your-registry/osticket --tag v1.0.0
   ```

3. Set up DNS for your domain (e.g., osticket.example.com)

4. Run the deployment script with your specific parameters:
   ```
   ./deploy.sh --repo your-registry/osticket --tag v1.0.0 --domain osticket.example.com
   ```

5. Monitor the deployment with:
   ```
   ./monitor.sh
   ```

6. Set up monitoring with Prometheus and Grafana (optional)

7. Set up backups for persistent data (recommended)

This Kubernetes deployment follows industry best practices for running PHP applications in Kubernetes, with separate containers for PHP-FPM and Nginx, proper resource allocation, security configurations, multi-architecture support, and scalability features.
