FROM php:8.5-fpm-alpine

# Install system dependencies - each package separately for better caching
RUN apk add --no-cache libzip-dev
RUN apk add --no-cache zip
RUN apk add --no-cache unzip
RUN apk add --no-cache postgresql-dev
RUN apk add --no-cache oniguruma-dev

# Install PHP extensions - each extension separately for better caching
RUN docker-php-ext-install pdo
RUN docker-php-ext-install pdo_pgsql
RUN docker-php-ext-install mbstring
RUN docker-php-ext-install pcntl
RUN docker-php-ext-install zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer.json first for better caching
COPY composer.json ./

# Copy composer.lock if it exists
COPY composer.lock* ./

# Install PHP dependencies (skip scripts since artisan isn't available yet)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts || \
    composer update --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# Copy application files
COPY . .

# Run post-install scripts now that all files are available
RUN composer dump-autoload --optimize --no-interaction
RUN php artisan package:discover --ansi

# Set permissions - each operation separately for better caching
RUN chown -R www-data:www-data /app
RUN chmod -R 755 /app/storage
RUN chmod -R 755 /app/bootstrap/cache

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
