FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    curl \
    sqlite \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install \
        pdo_sqlite \
        mbstring \
        zip \
        pcntl

WORKDIR /var/www/mcp-test

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --no-interaction --optimize-autoloader \
    && php artisan config:cache \
    && php artisan route:cache

EXPOSE 8080

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
