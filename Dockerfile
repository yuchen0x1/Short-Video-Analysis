FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        curl \
    && docker-php-ext-install -j$(nproc) zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js for a_bogus signature generation
COPY --from=node:18-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:18-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader \
    && chmod -R 755 storage/ \
    && chmod +x /app/docker-entrypoint.sh

# php -S 默认单 worker，一次慢解析会阻塞全部请求
ENV PHP_CLI_SERVER_WORKERS=8

EXPOSE 8000

ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/router.php"]
