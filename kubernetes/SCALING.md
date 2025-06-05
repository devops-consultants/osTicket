# OSTicket Kubernetes Scaling Guide

This document provides guidance on scaling your OSTicket deployment in Kubernetes to handle increased load and maintain high availability.

## Architecture Overview

The OSTicket Kubernetes deployment uses a microservices architecture with the following components:

1. **PHP-FPM Pods** - Handle PHP processing
2. **Nginx Pods** - Serve static content and proxy requests to PHP-FPM
3. **MySQL StatefulSet** - Database storage
4. **Persistent Volumes** - Store plugins, translations, and other persistent data

## Horizontal Scaling (Pod Count)

### Automatic Scaling with HPA

The deployment includes Horizontal Pod Autoscalers (HPAs) for PHP-FPM and Nginx deployments. These automatically scale based on CPU and memory utilization:

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: php-fpm-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: php-fpm
  minReplicas: 2
  maxReplicas: 10
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
```

### Manual Scaling

To manually scale the number of pods:

```bash
# Scale PHP-FPM pods
kubectl -n osticket scale deployment php-fpm --replicas=4

# Scale Nginx pods
kubectl -n osticket scale deployment nginx --replicas=4
```

## Vertical Scaling (Resource Allocation)

### PHP-FPM Tuning

To optimize PHP-FPM for higher traffic, adjust the following:

1. **PHP-FPM Pool Configuration** (in ConfigMap):
   ```
   pm = dynamic
   pm.max_children = 20
   pm.start_servers = 5
   pm.min_spare_servers = 3
   pm.max_spare_servers = 7
   ```

2. **Container Resources** (in Deployment):
   ```yaml
   resources:
     limits:
       memory: "512Mi"
       cpu: "1000m"
     requests:
       memory: "256Mi"
       cpu: "200m"
   ```

### MySQL Scaling

For larger installations, scale MySQL resources:

```yaml
resources:
  limits:
    cpu: 2000m
    memory: 4Gi
  requests:
    cpu: 500m
    memory: 1Gi
```

Consider adding MySQL replication for read scaling by implementing a MySQL Operator.

## High Traffic Optimizations

### Nginx Optimizations

Update the Nginx ConfigMap with these optimizations:

```
worker_processes auto;
worker_connections 4096;
keepalive_timeout 65;
gzip on;
gzip_comp_level 5;
gzip_types text/plain text/css application/javascript application/json;
client_max_body_size 64m;
```

### PHP Optimizations

Update the PHP ConfigMap for better performance:

```
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.revalidate_freq = 0
opcache.validate_timestamps = 0 (in production)
```

### Database Optimizations

For MySQL:

1. Increase InnoDB buffer pool size
2. Use persistent volumes with high IOPS
3. Consider implementing connection pooling
4. Optimize indexes for common queries

## Monitoring Performance

Use Prometheus and Grafana for monitoring:

1. **Install Prometheus Operator**:
   ```bash
   helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
   helm install prometheus prometheus-community/kube-prometheus-stack -n monitoring
   ```

2. **Add ServiceMonitor** for OSTicket:
   ```yaml
   apiVersion: monitoring.coreos.com/v1
   kind: ServiceMonitor
   metadata:
     name: osticket
     namespace: monitoring
   spec:
     selector:
       matchLabels:
         app: nginx
     endpoints:
     - port: http
       path: /metrics
     namespaceSelector:
       matchNames:
       - osticket
   ```

## Scaling for Global Deployments

For multi-region deployments:

1. Deploy OSTicket in multiple Kubernetes clusters across regions
2. Use a global load balancer (like AWS Global Accelerator or Cloudflare)
3. Implement data replication strategies for MySQL
4. Consider read replicas in each region

## Capacity Planning

Recommended starting configuration by traffic level:

| Traffic Level | PHP-FPM Replicas | Nginx Replicas | PHP Memory | Database Size | Cache |
|---------------|-----------------|---------------|-----------|--------------|-------|
| Low (<100/day)| 2               | 2             | 128Mi     | 1Gi          | None  |
| Medium        | 3-5             | 3-5           | 256Mi     | 5Gi          | Redis |
| High          | 5-10            | 5-10          | 512Mi     | 10Gi+        | Redis |
| Enterprise    | 10+             | 10+           | 1Gi+      | 50Gi+        | Redis + CDN |
