FROM php:8.2-fpm

# Systemabhängigkeiten installieren
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo_mysql \
    zip \
    intl

# Composer installieren
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# PHP-Konfiguration für Symfony optimieren
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory-limit.ini

WORKDIR /var/www/html

# Symfony-CLI installieren
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get install -y symfony-cli

CMD ["php-fpm"]

EXPOSE 9000