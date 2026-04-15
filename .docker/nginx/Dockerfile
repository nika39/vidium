FROM nginx:1.29-alpine

# Install logrotate, crond (busybox provides crond by default on alpine, but let's be sure), and curl for healthcheck
RUN apk add --no-cache logrotate curl tzdata openssl

# Remove default nginx config
RUN rm /etc/nginx/conf.d/default.conf

# To allow logrotate to work properly on nginx alpine image,
# we need to remove the symlinks that forward logs to stdout/stderr
# and replace them with actual log files.
RUN unlink /var/log/nginx/access.log && \
    unlink /var/log/nginx/error.log && \
    touch /var/log/nginx/access.log && \
    touch /var/log/nginx/error.log && \
    chown nginx:nginx /var/log/nginx/access.log /var/log/nginx/error.log

RUN mkdir -p /etc/nginx/certs
COPY templates-prod /etc/nginx/templates
COPY logrotate.conf /etc/logrotate.d/nginx

# Setup entrypoint script for starting background crond and certs
COPY entrypoint.sh /docker-entrypoint.d/99-start-container.sh
RUN chmod +x /docker-entrypoint.d/99-start-container.sh

# Healthcheck configuration based on curl testing local health endpoint
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/health || exit 1
