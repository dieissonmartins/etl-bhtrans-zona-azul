FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    wget \
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

COPY composer.sh /tmp/composer.sh

RUN chmod +x /tmp/composer.sh

RUN /tmp/composer.sh && rm /tmp/composer.sh -f

# Set work dir
WORKDIR /var/www

# Copy itens
COPY . .

# Copy the shell script into the container
COPY install.sh /tmp/install.sh

# Grant execute permission to the script
RUN chmod +x /tmp/install.sh

# Run the shell script during the build process
RUN /tmp/install.sh
