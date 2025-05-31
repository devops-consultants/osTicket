#!/bin/bash
# Build and push multi-arch Docker images for Kubernetes deployment

set -e

# Check for required commands
function check_command() {
  if ! command -v $1 &> /dev/null; then
    echo "Error: $1 is required but not installed."
    return 1
  fi
  return 0
}

# Check for QEMU if building for multiple architectures
function setup_qemu() {
  if [[ "$PLATFORMS" == *"arm64"* ]] || [[ "$PLATFORMS" == *"arm/v7"* ]]; then
    echo "Setting up QEMU for cross-platform builds..."
    if ! docker run --privileged --rm tonistiigi/binfmt:latest --install all; then
      echo "Failed to set up QEMU. Multi-architecture builds may fail."
    fi
  fi
}

# Default values
IMAGE_REPO="your-registry/osticket"
IMAGE_TAG="latest"
PUSH=false
PLATFORMS="linux/amd64,linux/arm64"
BUILDX=true

# Print usage information
function print_usage() {
  echo "Usage: $0 [options]"
  echo ""
  echo "Options:"
  echo "  -r, --repo           Docker image repository (default: your-registry/osticket)"
  echo "  -t, --tag            Docker image tag (default: latest)"
  echo "  -p, --push           Push images after building"
  echo "  --platforms          Comma-separated list of platforms (default: linux/amd64,linux/arm64)"
  echo "  --no-buildx          Don't use buildx for multi-arch images"
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
    -p|--push)
      PUSH=true
      shift
      ;;
    --platforms)
      PLATFORMS="$2"
      shift 2
      ;;
    --no-buildx)
      BUILDX=false
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

# Move to project root directory
cd "$(dirname "$0")/.."

# Check for required commands
check_command docker || exit 1

# Only check for buildx if we're using it
if [ "$BUILDX" = true ]; then
  if ! docker buildx version > /dev/null 2>&1; then
    echo "Error: Docker Buildx is required for multi-arch builds. Please install Docker Buildx or use --no-buildx flag."
    exit 1
  fi
  
  # Set up QEMU for cross-platform builds if needed
  setup_qemu
fi

echo "Building osTicket Docker images for platforms: $PLATFORMS"
echo "PHP-FPM Image: $IMAGE_REPO:$IMAGE_TAG"
echo "Nginx Image: $IMAGE_REPO-nginx:$IMAGE_TAG"

# Set up Docker Buildx if needed
if [ "$BUILDX" = true ]; then
  # Check if docker buildx is available
  if ! docker buildx version > /dev/null 2>&1; then
    echo "Error: Docker Buildx not available. Please install Docker Buildx or use --no-buildx flag."
    exit 1
  fi

  # Ensure we have a builder instance
  BUILDER_NAME="osticket-multiarch-builder"
  
  # Check if the builder already exists
  if ! docker buildx inspect "$BUILDER_NAME" > /dev/null 2>&1; then
    echo "Creating new buildx builder: $BUILDER_NAME"
    docker buildx create --name "$BUILDER_NAME" --driver docker-container --bootstrap
  fi
  
  # Use the builder
  docker buildx use "$BUILDER_NAME"
  
  # Build the PHP-FPM image
  echo "Building multi-architecture PHP-FPM image..."
  DOCKER_BUILDX_CMD="docker buildx build --platform $PLATFORMS --output='type=image'"
  
  if [ "$PUSH" = true ]; then
    DOCKER_BUILDX_CMD="$DOCKER_BUILDX_CMD --push"
  else
    DOCKER_BUILDX_CMD="$DOCKER_BUILDX_CMD --load"
  fi
  
  $DOCKER_BUILDX_CMD \
    -t "$IMAGE_REPO:$IMAGE_TAG" \
    -f docker/Dockerfile \
    --target production .
  
  # Build the Nginx image
  echo "Building multi-architecture Nginx image..."
  $DOCKER_BUILDX_CMD \
    -t "$IMAGE_REPO-nginx:$IMAGE_TAG" \
    -f docker/Dockerfile \
    --target nginx .
else
  # Use regular docker build (single architecture)
  echo "Using standard docker build (not multi-arch)"
  
  # Build the PHP-FPM image
  docker build -t "$IMAGE_REPO:$IMAGE_TAG" \
    -f docker/Dockerfile \
    --target production .
  
  echo "Building osTicket Nginx image..."
  docker build -t "$IMAGE_REPO-nginx:$IMAGE_TAG" \
    -f docker/Dockerfile \
    --target nginx .
fi

# Push images if requested for non-buildx builds (buildx pushes directly with --push)
if [ "$PUSH" = true ] && [ "$BUILDX" = false ]; then
  echo "Pushing images to repository..."
  docker push "$IMAGE_REPO:$IMAGE_TAG"
  docker push "$IMAGE_REPO-nginx:$IMAGE_TAG"
fi

if [ "$PUSH" = true ]; then
  echo "Images pushed successfully:"
  echo "  $IMAGE_REPO:$IMAGE_TAG"
  echo "  $IMAGE_REPO-nginx:$IMAGE_TAG"
  echo "  Platforms: $PLATFORMS"
  
  # Update Kubernetes deployment files
  echo "To update Kubernetes deployment files:"
  echo "cd kubernetes"
  echo "sed -i 's|IMAGE_REPOSITORY|$IMAGE_REPO|g' kustomization.yaml"
  echo "sed -i 's|IMAGE_TAG|$IMAGE_TAG|g' kustomization.yaml"
fi

echo "Done!"
