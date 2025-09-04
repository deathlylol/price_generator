#!/bin/bash

echo "=== Генератор ценников ==="
echo ""

# Проверяем наличие Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker не установлен. Установите Docker и попробуйте снова."
    exit 1
fi

# Проверяем наличие docker-compose
if ! command -v docker-compose &> /dev/null; then
    echo "❌ docker-compose не установлен. Установите docker-compose и попробуйте снова."
    exit 1
fi

echo "🔨 Собираем Docker образ..."
docker-compose -f docker/docker-compose.yml build

if [ $? -ne 0 ]; then
    echo "❌ Ошибка при сборке образа"
    exit 1
fi

echo ""
echo "🚀 Запускаем генератор ценников..."
echo ""

# Запускаем генератор
docker-compose -f docker/docker-compose.yml run --rm price-generator

echo ""
echo "✅ Генерация завершена!"
echo "📁 Результаты сохранены в папке results/"
echo ""
echo "Для просмотра результатов:"
echo "  ls -la results/*/"
