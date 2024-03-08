FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo_mysql mbstring exif pcntl bcmath gd

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Config xdebug
COPY xdebug.ini "${PHP_INI_DIR}/conf.d"

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


# Set work dir
WORKDIR /var/www/html

# Copy itens
COPY . .

# Install project dependencies using Composer
RUN composer install --no-scripts --no-interaction --prefer-dist

# Update project dependencies using Composer
RUN composer update --no-scripts --no-interaction --prefer-dist

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*