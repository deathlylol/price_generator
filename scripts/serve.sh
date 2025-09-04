#!/bin/bash

# Скрипт для запуска локального веб-сервера для просмотра ценников

echo "=== Локальный веб-сервер для ценников ==="
echo ""

# Проверяем наличие Python
if command -v python3 &> /dev/null; then
    echo "🚀 Запускаем веб-сервер на Python..."
    echo "📱 Откройте браузер и перейдите по адресу:"
    echo "   http://localhost:8000"
    echo ""
    echo "📁 Структура:"
    echo "   http://localhost:8000/results/accessories/ - аксессуары"
    echo "   http://localhost:8000/results/promotions/  - акции"
    echo "   http://localhost:8000/results/simple/      - обычные товары"
    echo ""
    echo "⏹️  Для остановки нажмите Ctrl+C"
    echo ""
    
    cd "$(dirname "$0")/.."
    python3 -m http.server 8000
    
elif command -v php &> /dev/null; then
    echo "🚀 Запускаем веб-сервер на PHP..."
    echo "📱 Откройте браузер и перейдите по адресу:"
    echo "   http://localhost:8000"
    echo ""
    echo "📁 Структура:"
    echo "   http://localhost:8000/results/accessories/ - аксессуары"
    echo "   http://localhost:8000/results/promotions/  - акции"
    echo "   http://localhost:8000/results/simple/      - обычные товары"
    echo ""
    echo "⏹️  Для остановки нажмите Ctrl+C"
    echo ""
    
    cd "$(dirname "$0")/.."
    php -S localhost:8000
    
else
    echo "❌ Не найден Python3 или PHP для запуска веб-сервера"
    echo "💡 Установите один из них:"
    echo "   sudo apt install python3"
    echo "   sudo apt install php"
    exit 1
fi
