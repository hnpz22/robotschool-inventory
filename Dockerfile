FROM php:8.1-apache

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        zip \
        gd

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Crear directorio de logs PHP
RUN mkdir -p /var/log/php && chown www-data:www-data /var/log/php

# Copiar configuración PHP personalizada
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

# Directorio de trabajo
WORKDIR /var/www/html

# Permisos correctos para uploads
RUN chown -R www-data:www-data /var/www/html
