FROM php:8.4-fpm

# Install system dependencies and PHP extensions (including sockets + redis + netcat)
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev \
    libzip-dev libpq-dev libjpeg-dev libfreetype6-dev \
    default-mysql-client netcat \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd sockets

# Avoid Git "dubious ownership" errors inside container
RUN git config --global --add safe.directory /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Temporary .env to allow Composer scripts to run
COPY .env.example .env

# Permissions and executable init script
RUN chown -R www-data:www-data /var/www/html \
    && chmod +x docker/laravel/init-migrate.sh

# Install Composer dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Entrypoint runs Laravel bootstrap + background workers
ENTRYPOINT ["./docker/laravel/init-migrate.sh"]
