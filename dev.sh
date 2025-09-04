#!/bin/bash

echo "=== Режим разработки ==="
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

echo "🔨 Собираем Docker образ для разработки..."
docker-compose -f docker/docker-compose.yml --profile dev build

if [ $? -ne 0 ]; then
    echo "❌ Ошибка при сборке образа"
    exit 1
fi

echo ""
echo "🚀 Запускаем контейнер для разработки..."
echo ""

# Запускаем контейнер для разработки
docker-compose -f docker/docker-compose.yml --profile dev up -d price-generator-dev

echo ""
echo "✅ Контейнер для разработки запущен!"
echo ""
echo "Для входа в контейнер используйте:"
echo "  docker exec -it price-generator-dev bash"
echo ""
echo "Для запуска генератора внутри контейнера:"
echo "  docker exec -it price-generator-dev php price_generator.php"
echo ""
echo "Для остановки контейнера:"
echo "  docker-compose -f docker/docker-compose.yml --profile dev down"
