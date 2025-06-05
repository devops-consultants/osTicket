#!/bin/bash
# OSTicket Kubernetes Deployment Script

set -e

# Default values
NAMESPACE="osticket"
IMAGE_REPO="your-registry/osticket"
IMAGE_TAG="latest"
DOMAIN="osticket.example.com"
MYSQL_USER="osticket"
MYSQL_PASSWORD="$(openssl rand -base64 12)"
MYSQL_ROOT_PASSWORD="$(openssl rand -base64 16)"

# Print usage information
function print_usage() {
  echo "Usage: $0 [options]"
  echo ""
  echo "Options:"
  echo "  -n, --namespace      Kubernetes namespace (default: osticket)"
  echo "  -r, --repo           Docker image repository (default: your-registry/osticket)"
  echo "  -t, --tag            Docker image tag (default: latest)"
  echo "  -d, --domain         Domain name for ingress (default: osticket.example.com)"
  echo "  -u, --mysql-user     MySQL username (default: osticket)"
  echo "  -p, --mysql-password MySQL password (default: random)"
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
    -r|--repo)
      IMAGE_REPO="$2"
      shift 2
      ;;
    -t|--tag)
      IMAGE_TAG="$2"
      shift 2
      ;;
    -d|--domain)
      DOMAIN="$2"
      shift 2
      ;;
    -u|--mysql-user)
      MYSQL_USER="$2"
      shift 2
      ;;
    -p|--mysql-password)
      MYSQL_PASSWORD="$2"
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

# Encode secrets
MYSQL_USER_B64=$(echo -n "${MYSQL_USER}" | base64)
MYSQL_PASSWORD_B64=$(echo -n "${MYSQL_PASSWORD}" | base64)
MYSQL_ROOT_PASSWORD_B64=$(echo -n "${MYSQL_ROOT_PASSWORD}" | base64)

# Create a temporary directory
TEMP_DIR=$(mktemp -d)
trap 'rm -rf ${TEMP_DIR}' EXIT

# Copy all YAML files to the temp directory
cp -r *.yaml "${TEMP_DIR}/"

# Update secrets
sed -i "s/MYSQL_USER: .*$/MYSQL_USER: ${MYSQL_USER_B64}/" "${TEMP_DIR}/osticket-secrets.yaml"
sed -i "s/MYSQL_PASSWORD: .*$/MYSQL_PASSWORD: ${MYSQL_PASSWORD_B64}/" "${TEMP_DIR}/osticket-secrets.yaml"

# Update image repository and tag
sed -i "s/newName: .*$/newName: ${IMAGE_REPO}/" "${TEMP_DIR}/kustomization.yaml"
sed -i "s/newTag: .*$/newTag: ${IMAGE_TAG}/" "${TEMP_DIR}/kustomization.yaml"

# Update domain in ingress
sed -i "s/host: .*$/host: ${DOMAIN}/" "${TEMP_DIR}/ingress.yaml"

# Create namespace if it doesn't exist
kubectl create namespace "${NAMESPACE}" --dry-run=client -o yaml | kubectl apply -f -

# Apply resources
kubectl apply -k "${TEMP_DIR}"

echo ""
echo "OSTicket deployment initiated in namespace: ${NAMESPACE}"
echo "MySQL User: ${MYSQL_USER}"
echo "MySQL Password: ${MYSQL_PASSWORD}"
echo "MySQL Root Password: ${MYSQL_ROOT_PASSWORD}"
echo ""
echo "To check deployment status:"
echo "  kubectl -n ${NAMESPACE} get pods"
echo ""
echo "Access OSTicket at: https://${DOMAIN} (once DNS is configured)"
echo ""
echo "Note: Save the MySQL credentials in a secure location!"
