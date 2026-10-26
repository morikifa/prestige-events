# База данных: структура, импорт и обслуживание

## 1. Состав базы

База `prestige_events` приведена к третьей нормальной форме и повторяет структуру из
технического задания. СУБД — MySQL 8 или MariaDB 10.4 и выше, кодировка `utf8mb4`.

| Таблица | Назначение | Ключевые связи |
| --- | --- | --- |
| `roles` | справочник ролей | — |
| `categories` | категории мероприятий | — |
| `venues` | площадки проведения | — |
| `settings` | параметры платформы (ключ — значение) | — |
| `users` | учётные записи | `role_id → roles.id` |
| `events` | мероприятия | `category_id → categories.id`, `venue_id → venues.id`, `organizer_id → users.id` |
| `event_program` | программа мероприятия | `event_id → events.id` (каскадное удаление) |
| `registrations` | записи участников и билеты | `event_id → events.id`, `user_id → users.id` |
| `waitlist` | лист ожидания | `event_id`, `user_id` |
| `notifications` | уведомления в кабинете | `user_id → users.id`, `event_id → events.id` |
| `action_log` | журнал действий | `user_id → users.id` (при удалении пользователя — `NULL`) |

Ограничения, которые защищают данные:

- `registrations` — уникальная пара `(event_id, user_id)`: один участник не запишется дважды;
- `registrations` — уникальный `qr_token`: код билета не повторяется;
- `waitlist` — уникальная пара `(event_id, user_id)` и положительная позиция;
- `events` — проверки `ends_at >= starts_at`, `capacity > 0`, допустимые статусы;
- `events.slug` — уникальный адрес страницы;
- `users.email` — уникальный логин;
- `action_log.details` — проверка корректного JSON (`JSON_VALID`).

Индексы созданы под реальные запросы: афиша (`status, starts_at`), фильтры по категории и
площадке, программа (`event_id, sort_order`), непрочитанные уведомления (`user_id, is_read`),
журнал по дате (`created_at`).

## 2. Импорт базы

### Способ 1. phpMyAdmin (XAMPP, OpenServer)

1. Откройте <http://localhost/phpmyadmin>.
2. Вкладка «Импорт» → выберите `database/schema.sql` → «Вперёд».
   Скрипт сам создаст базу `prestige_events` и все таблицы.
3. Снова «Импорт» → выберите `database/seed.sql` → «Вперёд».
4. Слева появится база `prestige_events` с 11 таблицами и заполненными справочниками.

### Способ 2. Командная строка

Из корня проекта:

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

Ключ `-p` не нужен, если у пользователя нет пароля.

### Способ 3. MySQL Workbench

1. Подключитесь к серверу.
2. **File → Open SQL Script** → `database/schema.sql` → выполните (иконка молнии).
3. Повторите для `database/seed.sql`.

Скрипт `schema.sql` начинается с удаления таблиц, поэтому его можно выполнять повторно:
база будет создана заново в чистом виде. Данные при этом пропадут — для сохранения
используйте резервную копию.

## 3. Проверка после импорта

Выполните в phpMyAdmin или SQLTools:

```sql
USE prestige_events;
SELECT COUNT(*) FROM roles;
SELECT COUNT(*) FROM categories;
SELECT COUNT(*) FROM events;
SELECT r.name AS role, u.last_name, u.email FROM users u JOIN roles r ON r.id = u.role_id;
```

Ожидаемый результат: 4 роли, 6 категорий, 7 мероприятий, 8 пользователей
(администратор, два организатора, участники).

Проверка связей и ограничений:

```sql
SELECT e.title, c.name AS category, v.name AS venue, u.last_name AS organizer
FROM events e
JOIN categories c ON c.id = e.category_id
LEFT JOIN venues v ON v.id = e.venue_id
JOIN users u ON u.id = e.organizer_id
ORDER BY e.starts_at;
```

## 4. Как приложение работает с базой

- подключение — `app/lib/db.php`, драйвер PDO, ошибки в виде исключений;
- все запросы — только через подготовленные выражения (`prepare`, `execute`);
- данные о мероприятиях и местах — `app/lib/events.php`;
- настройки платформы читаются из таблицы `settings` и кешируются на время запроса;
- при недоступной базе показывается страница с подсказками (`app/views/db-error.php`),
  а не «белый экран».

Чтобы изменить параметры подключения, отредактируйте `app/config.php`
или задайте переменные окружения `PRESTIGE_DB_HOST`, `PRESTIGE_DB_NAME`,
`PRESTIGE_DB_USER`, `PRESTIGE_DB_PASSWORD`.

## 5. Резервное копирование

Копии создаются средствами приложения: **Администрирование → Резервные копии → Создать копию**.
Файл `prestige-ГГГГММДД-ЧЧММСС.sql` сохраняется в `storage/backups/`.

- «Скачать» — забрать файл на другой носитель;
- «Восстановить» — заменить текущие данные содержимым копии;
- «Удалить» — очистить каталог.

Файл содержит `DELETE` и `INSERT` для всех таблиц, поэтому восстанавливается на любой
сервер MySQL. Для ежедневного копирования по расписанию удобно вызывать скрипт из
планировщика задач Windows (или cron на Linux):

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\prestige-events\tools\backup-cron.php
```

Скрипт `tools/backup-cron.php` создаёт копию без входа в интерфейс. Требование ТЗ —
хранить копии не менее 30 суток на носителе, отличном от основного.

## 6. Демонстрационная база без MySQL

Если MySQL недоступен (например, на чужом компьютере), можно собрать локальную SQLite-копию:

```bash
php tools/demo-db.php
```

Скрипт читает `database/schema.sql` и `database/seed.sql`, переносит структуру и данные
в `storage/demo.sqlite`. Затем:

```bash
PRESTIGE_DB_DRIVER=sqlite php -S localhost:8000 -t public
```

В PowerShell:

```powershell
$env:PRESTIGE_DB_DRIVER = "sqlite"
php -S localhost:8000 -t public
```

Демо-режим нужен только для показа интерфейса. Основной режим проекта — MySQL,
как указано в ТЗ.

## 7. Замечание о СУБД из технического задания

В ТЗ указан Microsoft SQL Server. Проект выполнен на MySQL/MariaDB, потому что связка
PHP + MS SQL на Linux требует отдельного драйвера `sqlsrv`, а учебный сервер колледжа
обычно работает на Apache/Nginx + MySQL. Структура таблиц, связей, ограничений и индексов
сохранена: имена таблиц и полей совпадают, типы подобраны эквивалентно
(`DATETIME2 → DATETIME`, `NVARCHAR → VARCHAR ... utf8mb4`, `ISJSON → JSON_VALID`).
Для переноса на MS SQL достаточно заменить блок создания таблиц на исходный скрипт из ТЗ
и указать в `app/config.php` драйвер `sqlsrv` с DSN `sqlsrv:Server=...;Database=...`.
