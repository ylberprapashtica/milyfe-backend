FROM php:8.5-fpm-alpine

# Install only necessary system dependencies
RUN apk add --no-cache \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev \
    oniguruma-dev

# Install only necessary PHP extensions for Laravel
RUN docker-php-ext-install pdo pdo_pgsql mbstring pcntl zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json ./
COPY composer.lock* ./

# Install PHP dependencies (skip scripts since artisan isn't available yet)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts || \
    composer update --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# Copy application files
COPY . .

# Run post-install scripts now that all files are available
RUN composer dump-autoload --optimize --no-interaction && php artisan package:discover --ansi

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
