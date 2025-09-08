# Используем официальный PHP образ с CLI
FROM php:8.2-cli

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Устанавливаем PHP расширения
RUN docker-php-ext-install \
    zip \
    gd \
    mbstring \
    intl

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/c                              omposer /usr/bin/composer

# Создаем рабочую директорию
WORKDIR /app

# Копируем composer файлы
COPY composer.json composer.lock* ./

# Устанавливаем зависимости
RUN composer install --no-dev --optimize-autoloader

# Копируем исходный код
COPY . .

# Создаем директории для результатов
RUN mkdir -p results/accessories results/promotions results/simple

# Проверяем наличие файла и устанавливаем права доступа
RUN ls -la && chmod +x price_generator.php

# Команда по умолчанию
CMD ["php", "price_generator.php"]
