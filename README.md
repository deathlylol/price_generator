# Генератор ценников

Простой генератор ценников из Excel файлов с использованием Docker.

## 🚀 Быстрый старт

### Запуск через Docker

```bash
# Генерация списка simple ценников
docker-compose -f docker/docker-compose.yml run --rm price-generator php price_generator.php simple-list

# Генерация списка promotions ценников  
docker-compose -f docker/docker/docker-compose.yml run --rm price-generator php price_generator.php promotions-list

# Генерация списка accessories ценников
docker-compose -f docker/docker-compose.yml run --rm price-generator php price_generator.php accessories-list

# Генерация списка simple_accessories ценников
docker-compose -f docker/docker-compose.yml run --rm price-generator php price_generator.php simple-accessories-list
```

### Просмотр результатов

```bash
# Запуск веб-сервера для просмотра
./scripts/serve.sh

# Откройте браузер: http://localhost:8000
```

## 📁 Структура проекта

```
prices/
├── price_generator.php          # Основной скрипт
├── excel/                      # Excel файлы с данными
│   ├── simple.xlsx
│   ├── promotions.xlsx
│   ├── accessories.xlsx
│   └── simple_accessories.xlsx
├── templates/                  # HTML шаблоны
│   ├── simple/
│   ├── promotions/
│   ├── accessories/
│   └── simple_accessories/
├── results/                    # Результаты генерации
│   ├── simple/simple_price_tags_list.html
│   ├── promotions/promotions_price_tags_list.html
│   ├── accessories/accessories_price_tags_list.html
│   └── simple_accessories/simple_accessories_price_tags_list.html
├── docker/                     # Docker конфигурация
└── scripts/serve.sh           # Веб-сервер для просмотра
```

## 📊 Режимы генерации

| Режим | Excel файл | Результат |
|-------|------------|-----------|
| `simple-list` | `simple.xlsx` | `simple_price_tags_list.html` |
| `promotions-list` | `promotions.xlsx` | `promotions_price_tags_list.html` |
| `accessories-list` | `accessories.xlsx` | `accessories_price_tags_list.html` |
| `simple-accessories-list` | `simple_accessories.xlsx` | `simple_accessories_price_tags_list.html` |

## 🔧 Требования

- Docker
- docker-compose

## 📝 Использование

1. Поместите данные в Excel файлы в папку `excel/`
2. Запустите нужную команду Docker
3. Результаты появятся в папке `results/`
4. Используйте `./scripts/serve.sh` для просмотра в браузере
