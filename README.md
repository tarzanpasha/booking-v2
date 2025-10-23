# Универсальная Система Бронирования на Laravel

## Описание проекта

Проект представляет собой универсальную систему бронирования ресурсов с гибкой системой расписаний. Система позволяет управлять компаниями, типами ресурсов, ресурсами и их расписаниями через REST API.

## Основные возможности

- **Управление компаниями** - создание и управление организациями
- **Система расписаний** - поддержка статических и динамических расписаний
- **Типы ресурсов** - категоризация ресурсов с общими настройками
- **Ресурсы** - управление конкретными объектами бронирования
- **REST API** - полный набор эндпоинтов для интеграции

## Модели данных

### Company (Компания)
- Основная сущность для группировки ресурсов
- Содержит базовую информацию об организации

### Timetable (Расписание)
- Поддерживает два типа: static (статическое) и dynamic (динамическое)
- Static: расписание по дням недели с праздниками
- Dynamic: расписание по конкретным датам
- Может быть привязано к ресурсам и типам ресурсов

### ResourceType (Тип ресурса)
- Определяет категорию ресурсов (например: "переговорная", "спортзал")
- Содержит общие настройки для всех ресурсов этого типа
- Может иметь собственное расписание

### Resource (Ресурс)
- Конкретный объект для бронирования
- Наследует настройки от типа ресурса
- Может переопределять настройки типа
- Может иметь собственное расписание

## Конфигурация ресурсов

Каждый ресурс и тип ресурса содержит конфигурацию:
- `slot_duration_minutes` - длительность слота в минутах
- `max_participants` - максимальное количество участников
- `require_confirmation` - требуется ли подтверждение
- `slot_strategy` - стратегия слотов (fixed/dinamic)

## Установка и настройка

### Требования
- PHP 8.1+
- Laravel 10+
- MySQL 8.0+
- Composer

### Установка

1. Установите зависимости:
composer install

2. Настройте файл окружения:
cp .env.example .env
php artisan key:generate

3. Настройте базу данных в .env:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8006
DB_DATABASE=booking
DB_USERNAME=booking
DB_PASSWORD=booking

4. Запустите миграции:
php artisan migrate

### Запуск с Docker

Проект включает Docker конфигурацию для быстрого развертывания:

Запуск контейнеров:
docker-compose up -d

Остановка контейнеров:
docker-compose down

Контейнеры предоставляют:
- MySQL на порту 8006
- phpMyAdmin на порту 8093

### Генерация тестовых данных

Используйте Artisan команды для создания тестовых расписаний:

Статическое расписание:
php artisan timetable:generate-static 1

Динамическое расписание на 30 дней:
php artisan timetable:generate-dynamic 1 30

## API Endpoints

### Компании
- GET /api/companies - список компаний
- POST /api/companies - создание компании
- GET /api/companies/{id} - получение компании
- PUT /api/companies/{id} - обновление компании
- DELETE /api/companies/{id} - удаление компании

### Расписания
- GET /api/timetables - список расписаний
- POST /api/timetables - создание расписания
- GET /api/timetables/{id} - получение расписания
- PUT /api/timetables/{id} - обновление расписания
- DELETE /api/timetables/{id} - удаление расписания

### Типы ресурсов
- GET /api/resource-types - список типов ресурсов
- POST /api/resource-types - создание типа ресурса
- GET /api/resource-types/{id} - получение типа ресурса
- PUT /api/resource-types/{id} - обновление типа ресурса
- DELETE /api/resource-types/{id} - удаление типа ресурса

### Ресурсы
- GET /api/resources - список ресурсов
- POST /api/resources - создание ресурса
- GET /api/resources/{id} - получение ресурса
- PUT /api/resources/{id} - обновление ресурса
- DELETE /api/resources/{id} - удаление ресурса

### Дополнительные эндпоинты для расписаний
- POST /api/timetables/{id}/attach-resource - прикрепить ресурс к расписанию
- POST /api/timetables/{id}/detach-resource - открепить ресурс от расписания
- POST /api/timetables/{id}/attach-resource-type - прикрепить тип ресурса к расписанию
- POST /api/timetables/{id}/detach-resource-type - открепить тип ресурса от расписания

## Примеры использования API

### Создание статического расписания
POST /api/timetables
{
  "company_id": 1,
  "type": "static",
  "schedule": {
    "days": {
      "monday": {
        "working_hours": {
          "start": "09:00",
          "end": "18:00"
        },
        "breaks": [
          {
            "start": "13:00",
            "end": "14:00"
          }
        ]
      }
    },
    "holidays": ["01-01", "03-08"]
  }
}

### Создание типа ресурса
POST /api/resource-types
{
  "company_id": 1,
  "type": "meeting_room",
  "name": "Meeting Room",
  "description": "Conference room for meetings",
  "resource_config": {
    "slot_duration_minutes": 60,
    "max_participants": 10,
    "require_confirmation": false,
    "slot_strategy": "fixed"
  }
}

## Структура проекта

- app/Models/ - Модели Eloquent
- app/Http/Controllers/ - Контроллеры API
- app/Http/Requests/ - Валидация запросов
- app/Http/Resources/ - Ресурсы для API ответов
- app/Actions/ - Бизнес-логика
- app/Enums/ - Перечисления
- app/ValueObjects/ - Объекты-значения
- app/Console/Commands/ - Artisan команды

## Тестирование

Для тестирования API используйте Postman коллекцию, расположенную в:
storage/app/exports/booking_system_postman_collection.json

## Дальнейшее развитие

- Система бронирования слотов
- Календарь доступности
- Уведомления и напоминания
- Система платежей
- Мультитенантность
- Панель администратора

## Лицензия

Проект распространяется под MIT License.
