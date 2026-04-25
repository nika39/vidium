FROM nginx:1.29-alpine

# Install logrotate, crond (busybox provides crond by default on alpine, but let's be sure), and curl for healthcheck
RUN apk add --no-cache logrotate curl tzdata openssl

RUN cp /usr/share/zoneinfo/Asia/Tbilisi /etc/localtime && \
    echo "Asia/Tbilisi" > /etc/timezone
RUN apk del tzdata

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

COPY nginx.conf /etc/nginx/nginx.conf
COPY templates-prod /etc/nginx/templates

RUN mkdir -p /var/log/nginx/logs.d /var/log/nginx/errors.d
RUN touch /var/log/nginx/logs.d/my_access.log /var/log/nginx/errors.d/my_error.log

RUN chmod 644 /etc/logrotate.d/nginx
RUN touch /var/lib/logrotate.status
RUN chmod 600 /var/lib/logrotate.status

RUN echo "0 * * * * logrotate -v /etc/logrotate.conf" >> /etc/crontabs/root

# Setup entrypoint script for starting background crond and certs
COPY entrypoint.sh /docker-entrypoint.d/99-start-container.sh
RUN chmod +x /docker-entrypoint.d/99-start-container.sh

# Healthcheck configuration based on curl testing local health endpoint
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/health || exit 1
