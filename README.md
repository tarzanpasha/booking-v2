# 📋 Документация проекта: Универсальная Система Бронирования Ресурсов

## 🎯 Общее описание проекта

**Универсальная Система Бронирования Ресурсов** - это комплексное решение для управления расписаниями и бронирования различных типов ресурсов в рамках компаний. Система поддерживает гибкую настройку рабочих графиков, различные стратегии генерации временных слотов и многоуровневую конфигурацию ресурсов.

---

## 🏗️ Архитектура системы

### Основные сущности

#### 1. **Company (Компания)**
- Базовая организационная единица
- Содержит ресурсы, расписания и бронирования
- `id` - уникальный идентификатор (используется для интеграции с внешними системами)

#### 2. **Timetable (Расписание)**
- Определяет рабочие часы и перерывы
- Два типа: **статическое** и **динамическое**

**Статическое расписание:**
```php
{
  "days": {
    "monday": {
      "working_hours": {"start": "09:00", "end": "18:00"},
      "breaks": [{"start": "13:00", "end": "14:00"}]
    },
    // ... другие дни
  },
  "holidays": ["01-01", "01-07"] // российские праздники
}
```

**Динамическое расписание:**
```php
{
  "dates": {
    "01-15": {
      "working_hours": {"start": "10:00", "end": "20:00"},
      "breaks": [{"start": "14:00", "end": "15:00"}]
    }
    // ... другие даты
  }
}
```

#### 3. **ResourceType (Тип ресурса)**
- Категория ресурсов (сотрудник, переговорная, оборудование)
- Содержит общую конфигурацию для всех ресурсов этого типа
- Может иметь собственное расписание

#### 4. **Resource (Ресурс)**
- Конкретный объект для бронирования (парикмахер Иван, переговорная №201)
- Наследует конфигурацию от типа ресурса, но может переопределять параметры
- Имеет приоритетное расписание (собственное > типа ресурса)

#### 5. **Booking (Бронирование)**
- Фактическая запись на временной слот
- Поддерживает индивидуальные и групповые бронирования
- Имеет статусный workflow

#### 6. **Booker (Бронирующий)**
- Абстрактная сущность для связи с внешними системами
- Polymorphic отношения для гибкости интеграции

---

## ⚙️ Конфигурация ресурсов (ResourceConfig)

### Параметры конфигурации:

```php
{
  "require_confirmation": false,      // Требовать подтверждение брони
  "slot_duration_minutes": 60,       // Длительность слота в минутах
  "max_participants": null,          // Макс. участников (null - индивидуальный)
  "slot_strategy": "fixed",          // Стратегия генерации слотов
  "min_advance_time": 60,            // Мин. время до брони (минуты)
  "cancellation_time": 120,          // Время для отмены (минуты)
  "reschedule_time": 240,            // Время для переноса (минуты)
  "reminder_time": null              // Время напоминания (минуты)
}
```

### Наследование конфигурации:
1. **Ресурс использует собственную конфигурацию**, если она задана
2. **Иначе наследует конфигурацию типа ресурса**
3. **Расписание**: собственное > типа ресурса

---

## 🎯 Алгоритмы генерации слотов

### 1. **Фиксированная стратегия (Fixed Strategy)**

**Алгоритм:**
```
1. Получить рабочие часы из расписания на указанную дату
2. Начать от начала рабочего дня с шагом = длительность слота
3. Для каждого потенциального слота:
   - Проверить, что слот полностью помещается в рабочие часы
   - Проверить, что слот не пересекается с перерывами
   - Проверить, что слот не пересекается с существующими бронированиями
4. Вернуть список доступных слотов
```

**Пример:**
```
Рабочие часы: 09:00-18:00
Перерыв: 13:00-14:00
Длительность слота: 60 минут

Доступные слоты:
09:00-10:00
10:00-11:00
11:00-12:00
12:00-13:00 ❌ (пересекается с перерывом)
14:00-15:00
15:00-16:00
16:00-17:00
17:00-18:00
```

### 2. **Динамическая стратегия (Dynamic Strategy)**

**Алгоритм:**
```
1. Получить рабочие часы и существующие бронирования
2. Создать список доступных периодов времени:
   - Начальный период: весь рабочий день
   - Вычесть существующие бронирования
   - Вычесть перерывы
3. Для каждого доступного периода:
   - Нарезать слоты от начала периода с шагом = длительность слота
   - Остановиться, когда следующий слот не помещается в период
4. Вернуть список доступных слотов
```

**Пример:**
```
Рабочие часы: 09:00-18:00
Существующие брони: 10:00-11:30, 14:00-15:00
Перерыв: 13:00-14:00
Длительность слота: 60 минут

Доступные периоды:
09:00-10:00, 11:30-13:00, 15:00-18:00

Доступные слоты:
09:00-10:00
11:30-12:30
12:30-13:00 ❌ (не помещается 60 минут)
15:00-16:00
16:00-17:00
17:00-18:00
```

---

## 🔄 Workflow бронирования

### 1. **Поиск доступных слотов**
```php
// Получить следующие 10 слотов начиная с сегодня
$slots = $bookingService->getNextAvailableSlots(
    $resource, 
    Carbon::now(), 
    10, 
    true
);
```

### 2. **Создание бронирования**
```php
// Для клиента (требует подтверждения если настроено)
$booking = $bookingService->createBooking(
    $resource,
    '2024-01-15 14:00:00',
    '2024-01-15 15:00:00',
    $bookerData,
    false // is_admin = false
);

// Для администратора (сразу подтвержденное)
$booking = $bookingService->createBooking(
    $resource,
    '2024-01-15 14:00:00',
    '2024-01-15 15:00:00',
    $bookerData,
    true // is_admin = true
);
```

### 3. **Валидации при создании брони:**
- **Минимальное время до брони** (min_advance_time)
- **Соответствие стратегии слотов** (для фиксированной стратегии)
- **Отсутствие пересечений** с существующими бронированиями
- **Доступность ресурса** в указанное время

### 4. **Управление статусами:**
```php
// Статусы бронирования
enum BookingStatus: string
{
    case PENDING = 'pending';              // Ожидает подтверждения
    case CONFIRMED = 'confirmed';          // Подтверждена
    case CANCELLED_BY_CLIENT = 'cancelled_by_client';  // Отменена клиентом
    case CANCELLED_BY_ADMIN = 'cancelled_by_admin';    // Отменена администратором
    case REJECTED = 'rejected';            // Отклонена
}
```

---

## 🎪 Особые сценарии

### 1. **Групповые бронирования**
```php
// Ресурс с max_participants > 1 считается групповым
$config = [
    "max_participants": 10,
    "slot_duration_minutes": 90
];

// На тренировку могут записаться до 10 человек одновременно
```

### 2. **Бронирование с подтверждением**
```php
$config = [
    "require_confirmation": true,
    "slot_duration_minutes": 30
];

// Бронь создается со статусом PENDING
// Администратор должен подтвердить через confirmBooking()
```

### 3. **Многослотовые бронирования**
```php
// Клиент может занять несколько последовательных слотов
$isAvailable = $bookingService->isSlotAvailable(
    $resource, 
    '2024-01-15 14:00:00', 
    4 // 4 слота по 30 минут = 2 часа
);
```

### 4. **Административные привилегии**
- **Игнорирование минимального времени** до брони
- **Создание броней в любое время** (не только по слотам)
- **Отмена в любое время** (игнорируя cancellation_time)
- **Перенос в любое время** (игнорируя reschedule_time)

---

## 📊 API Endpoints

### Core Entities (CRUD)
```
GET    /api/companies                    # Список компаний
POST   /api/companies                   # Создать компанию
GET    /api/companies/{id}              # Получить компанию
PUT    /api/companies/{id}              # Обновить компанию
DELETE /api/companies/{id}              # Удалить компанию

GET    /api/timetables                  # Список расписаний
POST   /api/timetables                  # Создать расписание
# ... аналогично для resource-types, resources
```

### Booking Functionality
```
GET    /api/booking/resources           # Ресурсы для бронирования
GET    /api/booking/{resource}/slots    # Доступные слоты ресурса
POST   /api/booking/create              # Создать бронирование
POST   /api/booking/{id}/confirm        # Подтвердить бронь
POST   /api/booking/{id}/cancel         # Отменить бронь
POST   /api/booking/{id}/reschedule     # Перенести бронь
GET    /api/booking/resource/{id}/bookings # Бронирования ресурса
GET    /api/booking/check               # Проверить доступность
```

### Timetable Management
```
POST   /api/timetables/{id}/attach-resource      # Прикрепить ресурс
POST   /api/timetables/{id}/detach-resource      # Открепить ресурс
POST   /api/timetables/{id}/attach-resource-type # Прикрепить тип ресурса
POST   /api/timetables/{id}/detach-resource-type # Открепить тип ресурса
```

---

## 🔧 Artisan Commands

### Генерация тестовых данных
```bash
php artisan booking:generate-test-data --company-id=1
```
Создает:
- Компанию с тестовыми данными
- Статическое и динамическое расписание
- Типы ресурсов (сотрудник, переговорная, тренировка)
- Конкретные ресурсы с разными конфигурациями
- Тестовые бронирования

### Генерация расписаний
```bash
# Статическое расписание
php artisan timetable:generate-static {company_id}

# Динамическое расписание
php artisan timetable:generate-dinamic {company_id} {days=30}
```

---

## 🗃️ Структура базы данных

### Основные таблицы:
```sql
companies           # Компании
timetables          # Расписания (связаны с companies)
resource_types      # Типы ресурсов (связаны с companies, timetables)
resources           # Ресурсы (связаны с companies, resource_types, timetables)
bookings            # Бронирования (связаны с companies, resources, timetables)
bookers             # Бронирующие (polymorphic отношения)
bookables           # Связующая таблица для polymorphic отношений
```

### Ключевые индексы:
- `resources(company_id, timetable_id)`
- `bookings(resource_id, start, end)`
- `bookings(company_id, start)`
- `bookers(external_id, type)`

---

## 🎨 Примеры использования

### 1. **Салон красоты**
```php
// Тип ресурса: Сотрудник
$employeeType = [
    "type": "employee",
    "resource_config": {
        "slot_duration_minutes": 45,
        "slot_strategy": "fixed",
        "require_confirmation": false,
        "min_advance_time": 60
    }
];

// Конкретный парикмахер
$hairdresser = [
    "resource_config": {
        "slot_duration_minutes": 60  // Переопределение для конкретного сотрудника
    }
];
```

### 2. **Коворкинг**
```php
// Тип ресурса: Переговорная
$meetingRoomType = [
    "type": "meeting_room", 
    "resource_config": {
        "slot_duration_minutes": 30,
        "slot_strategy": "dynamic",
        "max_participants": 8,
        "min_advance_time": 30
    }
];
```

### 3. **Фитнес-центр**
```php
// Тип ресурса: Групповая тренировка
$trainingType = [
    "type": "training",
    "resource_config": {
        "slot_duration_minutes": 90,
        "slot_strategy": "fixed", 
        "max_participants": 20,
        "require_confirmation": false,
        "min_advance_time": 1440  // За 24 часа
    }
];
```

---

## 🔍 Алгоритмы проверки доступности

### 1. **Проверка доступности слота**
```php
public function isSlotAvailable(Resource $resource, string $start, int $slots = 1): bool
{
    $duration = $config->slot_duration_minutes * $slots;
    $end = $start->copy()->addMinutes($duration);
    
    // Проверка пересечений с существующими бронированиями
    $overlapExists = Booking::where('resource_id', $resource->id)
        ->where('start', '<', $end)
        ->where('end', '>', $start)
        ->whereIn('status', ['pending', 'confirmed'])
        ->exists();
        
    return !$overlapExists;
}
```

### 2. **Проверка валидности времени**
```php
private function validateBookingTime(Resource $resource, Carbon $start, Carbon $end, ResourceConfig $config): void
{
    // Минимальное время до брони
    if ($start->diffInMinutes(now()) < $config->min_advance_time) {
        throw new Exception('Бронирование возможно только за X минут');
    }
    
    // Соответствие фиксированным слотам (для не-админов)
    if (!$isAdmin && $config->isFixedStrategy()) {
        if (!$this->isValidSlotTime($resource, $start, $end, $config)) {
            throw new Exception('Время не соответствует доступным слотам');
        }
    }
}
```

---

## 📈 Расширяемость системы

### 1. **Добавление новых типов ресурсов**
- Создать новый тип в `resource_types`
- Настроить конфигурацию в `resource_config`
- Система автоматически адаптируется

### 2. **Интеграция с внешними системами**
- Через модель `Booker` с polymorphic отношениями
- Поддержка `external_id` для синхронизации
- Гибкая система метаданных

### 3. **Кастомные стратегии слотов**
- Реализовать интерфейс стратегии
- Зарегистрировать в `SlotGenerationService`
- Использовать через конфигурацию ресурса

---

## 🚀 Запуск и развертывание

### 1. **Установка**
```bash
# Клонировать проект
git clone <repository>
cd project

# Установить зависимости
composer install

# Настроить .env
cp .env.example .env

# Запустить миграции
php artisan migrate

# Сгенерировать тестовые данные
php artisan booking:generate-test-data
```

### 2. **Docker окружение**
```bash
# Запуск полного стека
docker-compose -f Docker/docker-compose.yml up -d

# БД доступна на localhost:8006
# phpMyAdmin на localhost:8093
```

### 3. **Тестирование API**
```bash
# Запуск сервера
php artisan serve

# Тестирование через Postman
# Импортировать коллекцию: storage/app/exports/booking_system_postman_collection.json
```

---

## 💡 Ключевые особенности

### ✅ **Гибкость конфигурации**
- Наследование параметров от типа ресурса к ресурсу
- Приоритет собственной конфигурации ресурса
- Разделение статических и динамических расписаний

### ✅ **Мощная система слотов**
- Две стратегии генерации (фиксированная и динамическая)
- Учет перерывов и праздников
- Поддержка многослотовых бронирований

### ✅ **Разграничение прав**
- Разные правила для клиентов и администраторов
- Гибкая система подтверждений
- Ограничения по времени для операций

### ✅ **Масштабируемость**
- Поддержка групповых бронирований
- Polymorphic отношения для интеграций
- Модульная архитектура сервисов

---

## 🔄 Sequence Diagrams

### Процесс бронирования (клиент):
```
Клиент → API: GET /api/booking/{resource}/slots
API → SlotGenerationService: generateSlotsForDate()
SlotGenerationService → Database: Получить расписание и брони
SlotGenerationService: Сгенерировать доступные слоты
API → Клиент: Вернуть список слотов

Клиент → API: POST /api/booking/create
API → BookingService: createBooking()
BookingService: Валидация времени и доступности
BookingService → Database: Создать запись брони
BookingService → Event: Отправить BookingCreated
API → Клиент: Вернуть результат бронирования
```

### Процесс подтверждения брони:
```
Админ → API: POST /api/booking/{id}/confirm
API → BookingService: confirmBooking()
BookingService → Database: Обновить статус на "confirmed"
BookingService → Event: Отправить BookingConfirmed
API → Админ: Вернуть результат
```

---

## 🛠️ Технические детали реализации

### Модели данных:

**Company:**
```php
class Company extends Model
{
    protected $fillable = ['id', 'name', 'description'];
    
    public function timetables() { return $this->hasMany(Timetable::class); }
    public function resourceTypes() { return $this->hasMany(ResourceType::class); }
    public function resources() { return $this->hasMany(Resource::class); }
    public function bookings() { return $this->hasMany(Booking::class); }
}
```

**Resource (с конфигурацией):**
```php
class Resource extends Model
{
    use HasResourceConfig;
    
    protected $casts = [
        'options' => 'array',
        'payload' => 'array', 
        'resource_config' => 'array',
    ];
    
    public function getEffectiveTimetable()
    {
        return $this->timetable ?? $this->resourceType->timetable;
    }
}
```

### Сервисный слой:

**BookingService:**
```php
class BookingService
{
    public function __construct(private SlotGenerationService $slotService) {}
    
    public function createBooking(Resource $resource, string $start, string $end, array $bookerData = [], bool $isAdmin = false): Booking
    {
        return DB::transaction(function () use ($resource, $start, $end, $bookerData, $isAdmin) {
            // Валидации
            $this->validateBookingTime($resource, $start, $end, $config, $isAdmin);
            
            // Проверка доступности
            if (!$this->isRangeAvailable($resource, $start, $end)) {
                throw new Exception('Временной диапазон занят');
            }
            
            // Создание брони
            $booking = Booking::create([...]);
            
            // Привязка бронирующего
            if (!empty($bookerData)) {
                $this->attachBooker($booking, $bookerData);
            }
            
            return $booking;
        });
    }
}
```

---

## 📚 Логирование и мониторинг

### Каналы логирования:
- **booking** - все операции бронирования
- **slots** - генерация и проверка слотов
- **events** - системные события

### Ключевые метрики:
- Количество успешных бронирований
- Время ответа API
- Коэффициент использования ресурсов
- Частота отмен и переносов

---

## 🔒 Безопасность

### Валидации:
- SQL injection protection через Eloquent
- XSS protection через Blade templates
- CSRF protection для web-форм
- Rate limiting для API endpoints

### Авторизация:
- Разделение прав клиент/администратор
- Проверка владения ресурсами
- Валидация временных ограничений

---

Эта документация предоставляет полное понимание архитектуры, алгоритмов и workflow системы, позволяя разработчикам создавать аналогичные решения или расширять существующую функциональность.
