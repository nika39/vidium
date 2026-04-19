# Stage 1: Build PHP dependencies and Node.js assets
FROM php:8.5-cli-alpine AS builder

COPY --from=node:24-alpine /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-alpine /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm && \
    ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo_mysql bcmath zip intl

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY . .
RUN if [ ! -f .env ]; then cp .env.example .env; fi
RUN composer dump-autoload

RUN npm ci
RUN php artisan key:generate --force
RUN php artisan wayfinder:generate
RUN npm run build

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader


# Stage 2: Production PHP-FPM Image
FROM php:8.5-fpm-alpine AS production

WORKDIR /var/www/html

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo_mysql redis pcntl sockets bcmath zip intl opcache swoole-6.2.0

COPY . /var/www/html/

# Copy only what we need from builder
COPY --from=builder /var/www/html/vendor /var/www/html/vendor/
COPY --from=builder /var/www/html/bootstrap/cache /var/www/html/bootstrap/cache/
COPY --from=builder /var/www/html/public/build /var/www/html/public/build/

# Explicitly remove unneeded files just in case they slipped past .dockerignore
RUN rm -rf tests package.json package-lock.json || true

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
