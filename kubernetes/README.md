# OSTicket Kubernetes Deployment

This directory contains Kubernetes manifests for deploying OSTicket in a production-ready Kubernetes environment.

## Architecture Overview

The deployment follows industry best practices:

1. **Separation of Concerns**:
   - Split PHP-FPM and Nginx into separate deployments
   - Database in a dedicated StatefulSet
   - Configuration via ConfigMaps and Secrets

2. **Security**:
   - Non-root containers with minimal capabilities
   - Secure environment variable management
   - Resource limitations

3. **Scalability**:
   - Horizontal Pod Autoscalers
   - Optimized resource configurations
   - StatefulSet for database with persistent storage

4. **Reliability**:
   - Health checks for all components
   - Graceful rolling updates
   - Persistent volumes for critical data

## Prerequisites

- Kubernetes cluster (v1.20+)
- kubectl installed and configured
- kustomize installed (v4.0.0+)

## Deployment Instructions

1. **Configure Secrets**:
   Before deploying, update the `osticket-secrets.yaml` file with proper credentials:

   ```bash
   # Generate base64 encoded values
   echo -n "your_mysql_user" | base64
   echo -n "your_mysql_password" | base64
   
   # Update osticket-secrets.yaml with these values
   ```

2. **Configure Image Repository**:
   Update the `kustomization.yaml` file with your image repository details:

   ```yaml
   images:
   - name: ${IMAGE_REPOSITORY}
     newName: your-registry/osticket
     newTag: your-tag
   ```

3. **Configure Domain**:
   Update the host in `ingress.yaml` to match your domain:

   ```yaml
   rules:
   - host: your-osticket-domain.com
   ```

4. **Deploy**:
   ```bash
   kubectl apply -k .
   ```

5. **Verify Deployment**:
   ```bash
   kubectl -n osticket get pods
   kubectl -n osticket get services
   kubectl -n osticket get ingress
   ```

## Configuration Options

You can configure the following parameters in the `osticket-env-configmap.yaml`:

- `PHP_MAX_EXECUTION_TIME`: Maximum execution time for PHP scripts
- `PHP_MEMORY_LIMIT`: PHP memory limit per process
- `PHP_UPLOAD_MAX_FILESIZE`: Maximum allowed file upload size
- `PHP_POST_MAX_SIZE`: Maximum allowed POST request size
- `MYSQL_HOST`: MySQL database host
- `MYSQL_DATABASE`: MySQL database name

## Scaling

The deployment includes Horizontal Pod Autoscalers (HPAs) that will automatically scale the PHP-FPM and Nginx deployments based on CPU and memory usage.

## Maintenance

### Updating the Application

To update the application to a new version:

1. Build and push a new Docker image
2. Update the image tag in `kustomization.yaml`
3. Apply the changes:
   ```bash
   kubectl apply -k .
   ```

### Accessing Logs

```bash
# PHP-FPM logs
kubectl -n osticket logs -l app=php-fpm

# Nginx logs
kubectl -n osticket logs -l app=nginx

# MySQL logs
kubectl -n osticket logs -l app=mysql
```

## Backup and Restore

It's recommended to set up regular backups of:

1. MySQL database
2. Persistent volumes (plugins, i18n)

Example backup command:

```bash
kubectl -n osticket exec -it $(kubectl -n osticket get pods -l app=mysql -o jsonpath='{.items[0].metadata.name}') -- \
  mysqldump -u root -p osticket > osticket_backup_$(date +%Y%m%d).sql
```
