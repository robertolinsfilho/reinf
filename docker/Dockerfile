FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    zip \
    unzip \
    && docker-php-ext-install \
        pdo_mysql mbstring exif pcntl bcmath gd zip \
        soap dom curl openssl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN mkdir -p /var/www/html/public/uploads/xml \
    && mkdir -p /var/www/html/storage/certs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/public/uploads \
    && chmod -R 775 /var/www/html/storage

EXPOSE 9000

CMD ["php-fpm"]