# Docker конфигурация

Эта папка содержит все файлы, связанные с Docker для генератора ценников.

## Файлы

- `Dockerfile` - Основной Docker образ с PHP 8.2 и всеми необходимыми расширениями
- `docker-compose.yml` - Конфигурация Docker Compose для запуска сервисов
- `.dockerignore` - Исключения для Docker build context

## Использование

### Из корневой папки проекта:

```bash
# Обычный запуск
./run.sh

# Режим разработки
./dev.sh
```

### Прямое использование Docker Compose:

```bash
# Сборка образа
docker-compose -f docker/docker-compose.yml build

# Запуск генератора
docker-compose -f docker/docker-compose.yml run --rm price-generator

# Режим разработки
docker-compose -f docker/docker-compose.yml --profile dev up -d price-generator-dev
```

## Структура образа

- **Базовый образ**: PHP 8.2 CLI
- **Установленные расширения**: zip, gd, mbstring, intl
- **Зависимости**: PhpSpreadsheet для работы с Excel
- **Рабочая директория**: /app
- **Монтирование**: excel/, results/, templates/

## Разработка

Для разработки используйте профиль `dev`, который:
- Монтирует весь проект в контейнер
- Запускает контейнер в фоновом режиме
- Позволяет входить в контейнер для отладки
