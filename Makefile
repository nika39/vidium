.PHONY: all app web

GIT_USER  := $(shell git remote get-url origin | sed -e 's|.*github.com[/:]||' -e 's|/.*||')
GIT_REPO  := $(shell git remote get-url origin | sed -e 's|.*/||' -e 's|.git$$||')
REGISTRY  := ghcr.io
PLATFORM  := linux/amd64
TAG       := $(shell date +%Y%m%d%H%M%S)

IMAGE_APP := $(REGISTRY)/$(GIT_USER)/$(GIT_REPO)
IMAGE_WEB := $(REGISTRY)/$(GIT_USER)/$(GIT_REPO)-web

all: app web

app:
	@echo "🚀 Building and pushing app image..."
	docker buildx build --platform $(PLATFORM) \
		-f .docker/app-prod/Dockerfile \
		-t $(IMAGE_APP):latest \
		-t $(IMAGE_APP):$(TAG) \
		--push .
	@echo "✅ App image pushed: $(IMAGE_APP):$(TAG)"

web:
	@echo "🚀 Building and pushing web image..."
	docker buildx build --platform $(PLATFORM) \
		-f .docker/nginx/Dockerfile \
		-t $(IMAGE_WEB):latest \
		-t $(IMAGE_WEB):$(TAG) \
		--push .docker/nginx
	@echo "✅ Web image pushed: $(IMAGE_WEB):$(TAG)"
