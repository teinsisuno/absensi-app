# ==========================================
# Stage 1: Build PHP dependencies
# ==========================================
FROM composer:2.7 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# --no-scripts: post-autoload-dump manggil artisan yang belum ada di stage ini
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --ignore-platform-reqs --no-scripts

# ==========================================
# Stage 2: Build frontend Nuxt (SSG/SPA -> .output/public)
# ==========================================
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./frontend/
RUN cd frontend && npm ci
COPY frontend/ ./frontend/
RUN cd frontend && npm run generate

# ==========================================
# Stage 3: Production Image (Apache + PHP)
# ==========================================
FROM php:8.4-apache

WORKDIR /var/www/html

# System deps & PHP extensions — pdo_mysql (tenant DB) + redis (stancl cache tags)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql zip bcmath intl \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite untuk Laravel routing + SPA fallback
RUN a2enmod rewrite

# DocumentRoot menunjuk ke public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy application code (selektif — hindari .env, vendor, node_modules)
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY artisan composer.json composer.lock phpunit.xml ./

# Copy vendor dari composer stage
COPY --from=vendor /app/vendor/ ./vendor/

# Copy built frontend (Nuxt SSG) ke public/ Laravel — 1 origin (index.html + _nuxt + sw.js)
COPY --from=frontend /app/frontend/.output/public/ ./public/

# Entrypoint: siapin storage + migrate otomatis tiap container start
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permissions Laravel storage & cache (folder dibuat dulu — .dockerignore exclude storage)
RUN mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/framework/cache/data \
    && mkdir -p /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
