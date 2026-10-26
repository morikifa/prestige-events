# Установка окружения и запуск в VS Code

Инструкция рассчитана на Windows, но шаги почти совпадают для macOS и Linux.

## 1. Что нужно установить

| Компонент | Версия | Зачем |
| --- | --- | --- |
| PHP | 8.1 и выше | выполнение серверного кода |
| MySQL / MariaDB | 8.0 / 10.4 и выше | база данных |
| VS Code | любая актуальная | редактор и терминал |
| Браузер | Chrome, Edge, Firefox, Opera, Safari | проверка интерфейса |

В PHP должны быть включены расширения `pdo_mysql`, `mbstring`, `openssl`, `json`.
Они входят в стандартные сборки XAMPP и OpenServer Panel.

### Вариант А: XAMPP

1. Скачайте XAMPP с сайта apachefriends.org и установите с компонентами Apache, MySQL, PHP.
2. Запустите панель XAMPP и включите MySQL (кнопка Start).
3. Добавьте `C:\xampp\php` в переменную среды `PATH`, чтобы команда `php` работала в терминале.
   Проверка: откройте новый терминал и введите `php -v`.

### Вариант Б: OpenServer Panel

1. Установите OpenServer Panel, выберите модуль PHP 8.2 и MySQL 8.
2. Запустите сервер, откройте «Дополнительно → Консоль», проверьте `php -v`.
3. В конфигурации PHP включите `pdo_mysql` и `mbstring` (они обычно уже включены).

### Вариант В: PHP отдельно

1. Скачайте архив PHP для Windows (Thread Safe) с windows.php.net.
2. Распакуйте, например, в `C:\php`, скопируйте `php.ini-development` в `php.ini`.
3. В `php.ini` раскомментируйте строки:

```ini
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
```

4. Добавьте `C:\php` в `PATH`.
5. MySQL установите отдельно (MySQL Installer) либо используйте MariaDB.

## 2. Установка VS Code и расширений

1. Установите VS Code с code.visualstudio.com.
2. Откройте панель расширений (`Ctrl+Shift+X`) и установите:
   - **PHP Intelephense** — подсказки и переходы по коду;
   - **PHP Debug** — отладка через Xdebug (по желанию);
   - **EditorConfig for VS Code** — единые отступы;
   - **SQLTools** и **SQLTools MySQL/MariaDB Driver** — просмотр базы из редактора.
3. В репозитории уже лежит файл `.vscode/extensions.json` (рекомендации) и
   `.vscode/tasks.json` (задачи запуска). VS Code предложит установить расширения сам.

## 3. Открытие проекта

1. Скопируйте папку проекта, например в `C:\xampp\htdocs\prestige-events`.
2. В VS Code: **File → Open Folder** → выберите папку проекта.
3. Согласитесь с вопросом «Do you trust the authors?».
4. Слева появится дерево файлов: `app`, `database`, `docs`, `public`, `tools`.

## 4. Настройка подключения к базе

Откройте `app/config.php` и проверьте блок `db`:

```php
'db' => [
    'driver' => getenv('PRESTIGE_DB_DRIVER') ?: 'mysql',
    'host' => getenv('PRESTIGE_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('PRESTIGE_DB_PORT') ?: '3306',
    'name' => getenv('PRESTIGE_DB_NAME') ?: 'prestige_events',
    'user' => getenv('PRESTIGE_DB_USER') ?: 'root',
    'password' => getenv('PRESTIGE_DB_PASSWORD') ?: '',
],
```

В XAMPP и OpenServer пользователь `root` по умолчанию без пароля. Если вы задали свой пароль,
впишите его в `password`. Параметры можно не менять в файле, а задавать переменными окружения.

## 5. Импорт базы данных

Подробно — в `docs/02-baza-dannyh.md`. Коротко, в терминале VS Code (`Ctrl+~`):

```bash
mysql -u root < database/schema.sql
mysql -u root < database/seed.sql
```

Если MySQL установлен в XAMPP и не попал в `PATH`:

```bash
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
C:\xampp\mysql\bin\mysql.exe -u root < database/seed.sql
```

## 6. Запуск сайта

Самый простой способ — встроенный сервер PHP. В терминале VS Code, находясь в корне проекта:

```bash
php -S localhost:8000 -t public
```

В VS Code это же действие доступно через **Terminal → Run Task → «Сайт: запустить локальный сервер»**.

Откройте в браузере <http://localhost:8000>. Должна открыться афиша с крупной надписью
«PRESTIGE EVENTS» и списком мероприятий.

Остановить сервер: `Ctrl+C` в терминале.

### Запуск через Apache

Если проект лежит в `htdocs`, откройте <http://localhost/prestige-events/public/>.
Папкой сайта должна быть именно `public`, а не корень проекта: в корне находятся исходники
и настройки, к которым браузеру доступ не нужен.

## 7. Вход в систему

Нажмите «Войти» в правом верхнем углу и используйте одну из учётных записей:

| Роль | Логин | Пароль |
| --- | --- | --- |
| Администратор | admin@prestige.ru | admin1234 |
| Организатор | organizer@prestige.ru | org1234 |
| Участник | user@prestige.ru | user1234 |

После входа откроется кабинет, соответствующий роли. Меню и разделы у разных ролей различаются.

## 8. Полезные настройки VS Code

- Форматирование: `Shift+Alt+F` (для PHP установите расширение форматирования, например PHP Intelephense).
- Переход к определению функции: `F12`, поиск по проекту: `Ctrl+Shift+F`.
- Терминал: `Ctrl+~`, быстрый запуск задачи: `Ctrl+Shift+B`.
- Просмотр базы: панель SQLTools → Add Connection → MySQL → укажите `127.0.0.1`, пользователя
  `root` и базу `prestige_events`.

## 9. Если что-то не работает

| Симптом | Причина и решение |
| --- | --- |
| `php: команда не найдена` | PHP не добавлен в `PATH`. Перезапустите терминал или укажите полный путь к `php.exe` |
| Страница «Нет подключения к базе данных» | MySQL не запущен, неверный логин или база не создана |
| `could not find driver` | не включено расширение `pdo_mysql` в `php.ini` |
| Кракозябры вместо русских букв | база создана без `utf8mb4`; пересоздайте её скриптом `database/schema.sql` |
| Порт 8000 занят | запустите на другом порту: `php -S localhost:8080 -t public` |
| Стили не загрузились | сервер запущен без `-t public`, поэтому `/assets` не найден |
| Формы не сохраняются, пишет про устаревшую сессию | истёк CSRF-токен: обновите страницу и повторите действие |

Дальше: `docs/02-baza-dannyh.md`.
