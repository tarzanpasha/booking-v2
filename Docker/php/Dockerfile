FROM php:8.2-fpm

ARG PUID=1000
ARG PGID=1000

# Установим системные пакеты и необходимые расширения
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libpq-dev \
    libicu-dev libjpeg62-turbo-dev libfreetype6-dev libjpeg-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql zip gd intl opcache \
    && rm -rf /var/lib/apt/lists/*

# Установим Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Настройки PHP (опционально)
RUN { \
      echo "memory_limit=512M"; \
      echo "upload_max_filesize=64M"; \
      echo "post_max_size=64M"; \
      echo "opcache.enable=1"; \
      echo "opcache.validate_timestamps=1"; \
      echo "opcache.max_accelerated_files=20000"; \
    } > /usr/local/etc/php/conf.d/zz-custom.ini

# Создадим пользователя, совпадающего с вашим UID/GID (удобно в WSL)
RUN groupadd -g ${PGID} appgroup \
    && useradd -u ${PUID} -g appgroup -m appuser

USER appuser

WORKDIR /var/www/html
