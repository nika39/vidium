# Stage 1: Build environment
FROM oven/bun:1 AS builder
WORKDIR /app

# Install dependencies
COPY package.json bun.lock* ./
RUN bun install --production

# Copy source code
COPY . .

# Stage 2: Production environment (distroless, non-root)
FROM oven/bun:1-distroless
WORKDIR /app

# Distroless images run as non-root (uid 1000) by default
USER 1000

# Copy necessary files from the builder stage
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/server.ts ./
COPY --from=builder /app/package.json ./

EXPOSE 3000

CMD ["run", "server.ts"]

