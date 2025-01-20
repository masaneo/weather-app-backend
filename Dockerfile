# Użyj oficjalnego obrazu PHP z FPM
FROM php:8.2-fpm

# Zainstaluj Nginx
RUN apt-get update && apt-get install -y nginx

# Skopiuj plik konfiguracyjny Nginx
COPY ./nginx.conf /etc/nginx/nginx.conf

# Skopiuj kod źródłowy Laravel
WORKDIR /var/www/html
COPY . /var/www/html

# Zainstaluj zależności systemowe
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install pdo pdo_mysql mbstring gd zip

# Zainstaluj Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Ustawienia uprawnień Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Otwórz port HTTP
EXPOSE 80

# Start zarówno PHP-FPM, jak i Nginx
CMD service nginx start && php-fpm
