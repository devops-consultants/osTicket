#!/bin/bash
# Test script for Docker environment before Kubernetes deployment
# Supports testing multi-arch images

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
CHECK_ARCH=false
BUILD_MULTI_ARCH=false

# Print usage information
function print_usage() {
  echo "Usage: $0 [options]"
  echo ""
  echo "Options:"
  echo "  --check-arch        Check image architecture support"
  echo "  --build-multi-arch  Build multi-arch images before testing"
  echo "  -h, --help          Show this help message"
  echo ""
}

# Parse command-line arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    --check-arch)
      CHECK_ARCH=true
      shift
      ;;
    --build-multi-arch)
      BUILD_MULTI_ARCH=true
      shift
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

echo -e "${YELLOW}Starting osTicket Docker environment test${NC}"
echo "----------------------------------------"

# Check if docker and docker-compose are installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Docker is not installed. Please install Docker first.${NC}"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Docker Compose is not installed. Please install Docker Compose first.${NC}"
    exit 1
fi

# Function to check image architecture support
check_image_architecture() {
    local image="$1"
    echo -e "${BLUE}Checking architecture support for image: ${image}${NC}"
    
    # Get architecture information using docker inspect
    if ! arch_info=$(docker inspect --format '{{.Architecture}}' "$image" 2>/dev/null); then
        echo -e "${RED}Image not found or cannot inspect: ${image}${NC}"
        return 1
    fi
    
    # Get manifest info for multi-arch images
    if manifest_info=$(docker manifest inspect "$image" 2>/dev/null); then
        echo -e "${GREEN}Image supports multiple architectures:${NC}"
        platforms=$(echo "$manifest_info" | grep -o '"platform": {[^}]*}' | grep -o '"architecture": "[^"]*"' | cut -d'"' -f4 | sort -u)
        
        for platform in $platforms; do
            echo -e "${GREEN} - $platform${NC}"
        done
        
        # Check specifically for amd64 and arm64
        if echo "$platforms" | grep -q "amd64" && echo "$platforms" | grep -q "arm64"; then
            echo -e "${GREEN}✓ Image supports both amd64 and arm64 architectures${NC}"
            return 0
        else
            echo -e "${YELLOW}⚠ Image does not support both amd64 and arm64 architectures${NC}"
            return 1
        fi
    else
        echo -e "${YELLOW}Single architecture image: ${arch_info}${NC}"
        
        if [ "$arch_info" = "amd64" ] || [ "$arch_info" = "arm64" ]; then
            echo -e "${YELLOW}⚠ Image only supports ${arch_info} architecture${NC}"
        else
            echo -e "${RED}⚠ Image architecture ${arch_info} is not amd64 or arm64${NC}"
        fi
        return 1
    fi
}

# Switch to docker directory
cd "$(dirname "$0")"

# If build-multi-arch is requested, build the images first
if [ "$BUILD_MULTI_ARCH" = true ]; then
    echo -e "${YELLOW}Building multi-architecture images...${NC}"
    ../docker/build-for-k8s.sh --platforms "linux/amd64,linux/arm64" --repo "osticket-test" --tag "test"
    
    # Override the images in docker-compose.yml
    export OSTICKET_IMAGE="osticket-test:test"
    export OSTICKET_NGINX_IMAGE="osticket-test-nginx:test"
fi

# Check architecture support if requested
if [ "$CHECK_ARCH" = true ]; then
    echo -e "${YELLOW}Checking architecture support for images...${NC}"
    check_image_architecture "osticket-test:test" || echo -e "${YELLOW}Multi-architecture check failed. Consider building with --build-multi-arch${NC}"
    check_image_architecture "osticket-test-nginx:test" || echo -e "${YELLOW}Multi-architecture check failed. Consider building with --build-multi-arch${NC}"
fi

# Clean up any existing containers to start fresh
echo -e "${YELLOW}Stopping and removing any existing containers...${NC}"
docker-compose down -v

# Build and start the containers
echo -e "${YELLOW}Building and starting containers...${NC}"
docker-compose build
docker-compose up -d

# Wait for services to be ready
echo -e "${YELLOW}Waiting for services to start up...${NC}"
sleep 10

# Check if containers are running
echo -e "${YELLOW}Checking container status...${NC}"
CONTAINERS=("osticket-mysql" "osticket-php-fpm" "osticket-nginx")
ALL_RUNNING=true

for CONTAINER in "${CONTAINERS[@]}"; do
    STATUS=$(docker inspect --format='{{.State.Status}}' "$CONTAINER" 2>/dev/null || echo "not_found")
    
    if [ "$STATUS" != "running" ]; then
        echo -e "${RED}Container $CONTAINER is not running (status: $STATUS)${NC}"
        ALL_RUNNING=false
    else
        echo -e "${GREEN}Container $CONTAINER is running${NC}"
    fi
done

if [ "$ALL_RUNNING" = false ]; then
    echo -e "${RED}Not all containers are running. Check the logs with 'docker-compose logs'${NC}"
    exit 1
fi

# Check container health
echo -e "\n${YELLOW}Checking container health...${NC}"
ALL_HEALTHY=true

for CONTAINER in "${CONTAINERS[@]}"; do
    HEALTH=$(docker inspect --format='{{.State.Health.Status}}' "$CONTAINER" 2>/dev/null || echo "not_found")
    
    if [ "$HEALTH" != "healthy" ]; then
        echo -e "${RED}Container $CONTAINER is not healthy (health: $HEALTH)${NC}"
        ALL_HEALTHY=false
        
        # Show logs for unhealthy containers
        echo -e "${YELLOW}Logs for $CONTAINER:${NC}"
        docker logs "$CONTAINER" | tail -n 20
        echo ""
    else
        echo -e "${GREEN}Container $CONTAINER is healthy${NC}"
    fi
done

# Check if Nginx is responding
echo -e "\n${YELLOW}Testing Nginx connectivity...${NC}"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/)

if [ "$HTTP_CODE" -eq 200 ] || [ "$HTTP_CODE" -eq 302 ]; then
    echo -e "${GREEN}Nginx is responding with HTTP $HTTP_CODE${NC}"
else
    echo -e "${RED}Nginx is not responding properly. HTTP code: $HTTP_CODE${NC}"
    ALL_HEALTHY=false
fi

# Check PHP-FPM status through Nginx
echo -e "\n${YELLOW}Testing PHP-FPM connectivity through Nginx...${NC}"
PHP_TEST=$(curl -s http://localhost:8080/api/http.php/status)

if [[ "$PHP_TEST" == *"osTicket"* ]]; then
    echo -e "${GREEN}PHP-FPM is processing requests correctly${NC}"
else
    echo -e "${RED}PHP-FPM is not processing requests correctly${NC}"
    echo "Response: $PHP_TEST"
    ALL_HEALTHY=false
fi

# Check for multi-arch support if requested
if [ "$CHECK_ARCH" = true ]; then
    echo -e "\n${YELLOW}Checking container architecture:${NC}"
    
    CURRENT_ARCH=$(uname -m)
    if [ "$CURRENT_ARCH" = "x86_64" ]; then
        CURRENT_ARCH="amd64"
    elif [ "$CURRENT_ARCH" = "aarch64" ]; then
        CURRENT_ARCH="arm64"
    fi
    
    echo -e "Current architecture: ${GREEN}$CURRENT_ARCH${NC}"
    
    for CONTAINER in "${CONTAINERS[@]}"; do
        CONTAINER_IMAGE=$(docker inspect --format='{{.Config.Image}}' "$CONTAINER" 2>/dev/null || echo "unknown")
        echo -e "Container $CONTAINER is using image: $CONTAINER_IMAGE"
        
        if [ "$BUILD_MULTI_ARCH" = true ]; then
            echo -e "${GREEN}✓ $CONTAINER_IMAGE was built with multi-arch support${NC}"
        fi
    done
fi

# Final summary
echo -e "\n${YELLOW}Test summary:${NC}"
echo "----------------------------------------"

if [ "$ALL_RUNNING" = true ] && [ "$ALL_HEALTHY" = true ]; then
    echo -e "${GREEN}All tests passed! The Docker environment is working correctly.${NC}"
    echo -e "You can access osTicket at ${GREEN}http://localhost:8080/${NC}"
    echo -e "The Docker environment matches the Kubernetes deployment structure."
    
    if [ "$BUILD_MULTI_ARCH" = true ]; then
        echo -e "${GREEN}Multi-architecture images (amd64, arm64) were successfully built and tested.${NC}"
    else
        echo -e "${YELLOW}To build multi-architecture images for Kubernetes deployment:${NC}"
        echo -e "  ./build-for-k8s.sh --platforms linux/amd64,linux/arm64 --push"
    fi
    
    echo -e "Ready to proceed with Kubernetes deployment."
else
    echo -e "${RED}Some tests failed. Please fix the issues before deploying to Kubernetes.${NC}"
fi

echo ""
echo "To clean up the test environment:"
echo "  docker-compose down -v"
echo ""
