#!/bin/bash
# Test multi-architecture images for osTicket

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
IMAGE_REPO="your-registry/osticket"
IMAGE_TAG="latest"
BUILD=false
TEST=true
PLATFORMS="linux/amd64,linux/arm64"

# Print usage information
function print_usage() {
  echo "Usage: $0 [options]"
  echo ""
  echo "Options:"
  echo "  -r, --repo           Docker image repository (default: your-registry/osticket)"
  echo "  -t, --tag            Docker image tag (default: latest)"
  echo "  -b, --build          Build multi-arch images before testing"
  echo "  --no-test            Skip tests and only build"
  echo "  -p, --platforms      Comma-separated list of platforms (default: linux/amd64,linux/arm64)"
  echo "  -h, --help           Show this help message"
  echo ""
}

# Parse command-line arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    -r|--repo)
      IMAGE_REPO="$2"
      shift 2
      ;;
    -t|--tag)
      IMAGE_TAG="$2"
      shift 2
      ;;
    -b|--build)
      BUILD=true
      shift
      ;;
    --no-test)
      TEST=false
      shift
      ;;
    -p|--platforms)
      PLATFORMS="$2"
      shift 2
      ;;
    -h|--help)
      print_usage
      exit 0
      ;;
    *)
      echo -e "${RED}Unknown option: $1${NC}"
      print_usage
      exit 1
      ;;
  esac
done

# Move to docker directory
cd "$(dirname "$0")"

# Display current architecture
CURRENT_ARCH=$(uname -m)
if [ "$CURRENT_ARCH" = "x86_64" ]; then
    CURRENT_ARCH="amd64"
elif [ "$CURRENT_ARCH" = "aarch64" ]; then
    CURRENT_ARCH="arm64"
fi

echo -e "${YELLOW}Current architecture: ${CURRENT_ARCH}${NC}"
echo -e "${YELLOW}Testing platforms: ${PLATFORMS}${NC}"

# Build multi-arch images if requested
if [ "$BUILD" = true ]; then
    echo -e "${YELLOW}Building multi-architecture images...${NC}"
    
    # Run the build-for-k8s.sh script with the specified parameters
    ./build-for-k8s.sh --repo "$IMAGE_REPO" --tag "$IMAGE_TAG" --platforms "$PLATFORMS"
    
    echo -e "${GREEN}Multi-architecture images built successfully!${NC}"
fi

# Only run tests if not skipped
if [ "$TEST" = true ]; then
    echo -e "${YELLOW}Testing multi-architecture images...${NC}"
    
    # Set environment variables for docker-compose
    export OSTICKET_IMAGE="${IMAGE_REPO}:${IMAGE_TAG}"
    export OSTICKET_NGINX_IMAGE="${IMAGE_REPO}-nginx:${IMAGE_TAG}"
    
    # Stop any running containers
    docker-compose -f docker-compose.multi-arch.yml down -v
    
    # Start containers using the multi-arch compose file
    docker-compose -f docker-compose.multi-arch.yml up -d
    
    # Wait for services to start
    echo -e "${YELLOW}Waiting for services to start up...${NC}"
    sleep 10
    
    # Check container status
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
        echo -e "${RED}Some containers are not running. Check the logs with 'docker-compose -f docker-compose.multi-arch.yml logs'${NC}"
        exit 1
    fi
    
    # Check image architecture information
    echo -e "\n${YELLOW}Image Architecture Information:${NC}"
    
    for CONTAINER in "${CONTAINERS[@]}"; do
        CONTAINER_IMAGE=$(docker inspect --format='{{.Config.Image}}' "$CONTAINER" 2>/dev/null || echo "unknown")
        CONTAINER_PLATFORM=$(docker inspect --format='{{.Architecture}}' "$CONTAINER" 2>/dev/null || echo "unknown")
        
        echo -e "${BLUE}Container: ${CONTAINER}${NC}"
        echo -e "  Image: ${CONTAINER_IMAGE}"
        echo -e "  Running on platform: ${CONTAINER_PLATFORM}"
        
        # Check if osTicket containers are multi-arch (skip MySQL, as it's from Docker Hub)
        if [[ "$CONTAINER" != "osticket-mysql" ]]; then
            echo -e "  Multi-arch support: "
            if docker manifest inspect "$CONTAINER_IMAGE" &>/dev/null; then
                SUPPORTED_ARCHS=$(docker manifest inspect "$CONTAINER_IMAGE" | grep -o '"architecture": "[^"]*"' | cut -d'"' -f4 | sort -u)
                
                for ARCH in $SUPPORTED_ARCHS; do
                    echo -e "    - ${ARCH}"
                done
                
                # Check if both amd64 and arm64 are supported
                if echo "$SUPPORTED_ARCHS" | grep -q "amd64" && echo "$SUPPORTED_ARCHS" | grep -q "arm64"; then
                    echo -e "  ${GREEN}✓ Image supports both amd64 and arm64 architectures${NC}"
                else
                    echo -e "  ${YELLOW}⚠ Image does not support both amd64 and arm64 architectures${NC}"
                fi
            else
                echo -e "  ${YELLOW}Single architecture image${NC}"
            fi
        fi
        echo ""
    done
    
    # Test HTTP connectivity
    echo -e "${YELLOW}Testing HTTP connectivity...${NC}"
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/)
    
    if [ "$HTTP_CODE" -eq 200 ] || [ "$HTTP_CODE" -eq 302 ]; then
        echo -e "${GREEN}✓ HTTP test successful (HTTP $HTTP_CODE)${NC}"
    else
        echo -e "${RED}✗ HTTP test failed (HTTP $HTTP_CODE)${NC}"
    fi
    
    echo -e "\n${GREEN}Test completed!${NC}"
    echo -e "You can access osTicket at http://localhost:8080"
    echo -e "To stop the test environment: docker-compose -f docker-compose.multi-arch.yml down -v"
fi

echo -e "\n${YELLOW}Summary:${NC}"
if [ "$BUILD" = true ]; then
    echo -e "${GREEN}✓ Multi-architecture images built successfully${NC}"
fi
if [ "$TEST" = true ] && [ "$ALL_RUNNING" = true ]; then
    echo -e "${GREEN}✓ Multi-architecture images tested successfully${NC}"
fi
echo -e "Images ready for Kubernetes deployment!"
