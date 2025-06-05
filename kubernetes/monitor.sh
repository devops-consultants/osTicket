#!/bin/bash
# OSTicket Kubernetes Monitoring Script

set -e

NAMESPACE="osticket"

# Print usage information
function print_usage() {
  echo "Usage: $0 [options]"
  echo ""
  echo "Options:"
  echo "  -n, --namespace      Kubernetes namespace (default: osticket)"
  echo "  -h, --help           Show this help message"
  echo ""
}

# Parse command-line arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    -n|--namespace)
      NAMESPACE="$2"
      shift 2
      ;;
    -h|--help)
      print_usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      print_usage
      exit 1
      ;;
  esac
done

echo "=== OSTicket Deployment Status ==="
echo ""

# Check namespace
echo "Namespace: ${NAMESPACE}"
kubectl get namespace "${NAMESPACE}" -o wide || { echo "Namespace not found!"; exit 1; }
echo ""

# Check pods
echo "=== Pods ==="
kubectl -n "${NAMESPACE}" get pods -o wide
echo ""

# Check services
echo "=== Services ==="
kubectl -n "${NAMESPACE}" get services
echo ""

# Check deployments
echo "=== Deployments ==="
kubectl -n "${NAMESPACE}" get deployments
echo ""

# Check statefulsets
echo "=== StatefulSets ==="
kubectl -n "${NAMESPACE}" get statefulsets
echo ""

# Check PVCs
echo "=== Persistent Volume Claims ==="
kubectl -n "${NAMESPACE}" get pvc
echo ""

# Check HPAs
echo "=== Horizontal Pod Autoscalers ==="
kubectl -n "${NAMESPACE}" get hpa
echo ""

# Check Ingress
echo "=== Ingress ==="
kubectl -n "${NAMESPACE}" get ingress
echo ""

# Check pod resource usage
echo "=== Pod Resource Usage ==="
kubectl -n "${NAMESPACE}" top pod
echo ""

# Check node resource usage
echo "=== Node Resource Usage ==="
kubectl top node
echo ""

echo "=== OSTicket Health Check ==="
INGRESS_HOST=$(kubectl -n "${NAMESPACE}" get ingress osticket-ingress -o jsonpath='{.spec.rules[0].host}' 2>/dev/null || echo "Not configured")
echo "Ingress Host: ${INGRESS_HOST}"
echo "Health Check URL: http://${INGRESS_HOST}/api/http.php/status"
echo ""
echo "To check specific pod logs:"
echo "  kubectl -n ${NAMESPACE} logs -l app=php-fpm"
echo "  kubectl -n ${NAMESPACE} logs -l app=nginx"
echo ""
