#!/bin/bash

echo "Установка зависимостей для генератора ценников..."

# Проверяем наличие composer
if ! command -v composer &> /dev/null; then
    echo "Composer не найден. Устанавливаем..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

# Устанавливаем зависимости
composer install

echo "Установка завершена!"
echo "Для запуска генератора используйте: php price_generator.php"
