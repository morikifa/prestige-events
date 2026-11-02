CREATE TABLE roles (
id INTEGER PRIMARY KEY AUTOINCREMENT,
code TEXT NOT NULL UNIQUE,
name TEXT NOT NULL,
description TEXT
);
CREATE TABLE categories (
id INTEGER PRIMARY KEY AUTOINCREMENT,
name TEXT NOT NULL UNIQUE,
description TEXT,
created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE venues (
id INTEGER PRIMARY KEY AUTOINCREMENT,
name TEXT NOT NULL,
address TEXT NOT NULL,
capacity INTEGER,
created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
CHECK (capacity IS NULL OR capacity > 0)
);
CREATE TABLE settings (
setting_key TEXT PRIMARY KEY,
setting_value TEXT NOT NULL,
description TEXT,
updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE users (
id INTEGER PRIMARY KEY AUTOINCREMENT,
role_id INTEGER NOT NULL REFERENCES roles(id),
last_name TEXT NOT NULL,
first_name TEXT NOT NULL,
middle_name TEXT,
email TEXT NOT NULL UNIQUE,
phone TEXT,
password_hash TEXT NOT NULL,
status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'blocked', 'pending')),
email_verified_at TEXT,
created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE events (
id INTEGER PRIMARY KEY AUTOINCREMENT,
title TEXT NOT NULL,
slug TEXT NOT NULL UNIQUE,
category_id INTEGER NOT NULL REFERENCES categories(id),
venue_id INTEGER REFERENCES venues(id) ON DELETE SET NULL,
organizer_id INTEGER NOT NULL REFERENCES users(id),
description TEXT,
capacity INTEGER,
registration_opens_at TEXT,
registration_closes_at TEXT,
starts_at TEXT NOT NULL,
ends_at TEXT NOT NULL,
status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'moderation', 'published', 'cancelled', 'completed')),
created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
CHECK (capacity IS NULL OR capacity > 0),
CHECK (ends_at >= starts_at)
);
CREATE TABLE event_program (
id INTEGER PRIMARY KEY AUTOINCREMENT,
event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
title TEXT NOT NULL,
speaker TEXT,
place TEXT,
starts_at TEXT NOT NULL,
ends_at TEXT NOT NULL,
sort_order INTEGER NOT NULL DEFAULT 0,
CHECK (ends_at >= starts_at)
);
CREATE TABLE registrations (
id INTEGER PRIMARY KEY AUTOINCREMENT,
event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
status TEXT NOT NULL DEFAULT 'registered' CHECK (status IN ('registered', 'confirmed', 'attended', 'cancelled')),
qr_token TEXT NOT NULL UNIQUE,
attended_at TEXT,
registered_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
cancelled_at TEXT,
UNIQUE (event_id, user_id)
);
CREATE TABLE waitlist (
id INTEGER PRIMARY KEY AUTOINCREMENT,
event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
position INTEGER NOT NULL CHECK (position > 0),
status TEXT NOT NULL DEFAULT 'waiting' CHECK (status IN ('waiting', 'invited', 'registered', 'rejected')),
created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
UNIQUE (event_id, user_id)
);
CREATE TABLE notifications (
id INTEGER PRIMARY KEY AUTOINCREMENT,
user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
event_id INTEGER REFERENCES events(id) ON DELETE SET NULL,
type TEXT NOT NULL,
channel TEXT NOT NULL CHECK (channel IN ('email', 'web')),
subject TEXT NOT NULL,
message TEXT NOT NULL,
status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'failed')),
is_read INTEGER NOT NULL DEFAULT 0,
created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
sent_at TEXT
);
CREATE TABLE action_log (
id INTEGER PRIMARY KEY AUTOINCREMENT,
user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
action TEXT NOT NULL,
entity TEXT NOT NULL,
entity_id INTEGER,
details TEXT,
ip_address TEXT,
user_agent TEXT,
created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_events_status_dates ON events (status, starts_at);
CREATE INDEX idx_event_program_event_sort ON event_program (event_id, sort_order);
CREATE INDEX idx_notifications_user_unread ON notifications (user_id, is_read);
CREATE INDEX idx_registrations_event_status ON registrations (event_id, status);
INSERT INTO roles (code, name, description) VALUES ('guest', 'Гость', 'Неавторизованный посетитель: просмотр афиши и программы');
INSERT INTO roles (code, name, description) VALUES ('participant', 'Участник', 'Запись на мероприятия, получение билетов с QR, участие в листе ожидания');
INSERT INTO roles (code, name, description) VALUES ('organizer', 'Организатор', 'Создание и ведение мероприятий, учет участников, отметка посещения');
INSERT INTO roles (code, name, description) VALUES ('admin', 'Администратор', 'Полный контроль системы, справочников, аудит и сводные отчеты');
INSERT INTO categories (name, description) VALUES ('Конференция', 'Научно-практические и образовательные конференции');
INSERT INTO categories (name, description) VALUES ('Семинар', 'Обучающие семинары и практические мастер-классы');
INSERT INTO categories (name, description) VALUES ('Олимпиада', 'Студенческие олимпиады и конкурсы профессионального мастерства');
INSERT INTO categories (name, description) VALUES ('Хакатон', 'Командные соревнования по разработке программных решений');
INSERT INTO venues (name, address, capacity) VALUES ('Главный корпус, актовый зал', 'г. Краснодар, ул. Зиповская, 5', 250);
INSERT INTO venues (name, address, capacity) VALUES ('Корпус 2, аудитория 305', 'г. Краснодар, ул. Зиповская, 5', 40);
INSERT INTO venues (name, address, capacity) VALUES ('IT-лаборатория', 'г. Краснодар, ул. Зиповская, 5', 60);
INSERT INTO settings (setting_key, setting_value, description) VALUES ('platform_title', 'Prestige Events', 'Название платформы');
INSERT INTO settings (setting_key, setting_value, description) VALUES ('max_waitlist_size', '50', 'Максимальный размер листа ожидания на одно событие');
INSERT INTO settings (setting_key, setting_value, description) VALUES ('qr_token_salt', 'PrestigeSecretKey2026', 'Соль для криптографической генерации токена QR');
INSERT INTO settings (setting_key, setting_value, description) VALUES ('email_sender_name', 'Оргкомитет Prestige Events', 'Имя отправителя в почтовых уведомлениях');
INSERT INTO users (role_id, last_name, first_name, middle_name, email, phone, password_hash, status, email_verified_at) VALUES (4, 'Администратор', 'Системный', NULL, 'admin@prestige.local', '+7 900 000-00-01', '$2y$10$RFMVLGFfgrEPHFUO3hxR1OMCJppelRgDhWglWaouLo0cH6mQxVcjy', 'active', CURRENT_TIMESTAMP);
INSERT INTO users (role_id, last_name, first_name, middle_name, email, phone, password_hash, status, email_verified_at) VALUES (3, 'Волкова', 'Анна', 'Сергеевна', 'organizer@prestige.local', '+7 900 000-00-02', '$2y$10$becBr6El2tK0XNQbc4tFeuga7p9OicHgB1tqO8nb3l5tTeeuwSr6m', 'active', CURRENT_TIMESTAMP);
INSERT INTO users (role_id, last_name, first_name, middle_name, email, phone, password_hash, status, email_verified_at) VALUES (2, 'Студентов', 'Иван', 'Петрович', 'student@prestige.local', '+7 900 000-00-03', '$2y$10$.jp.egUjv18Htj4HGS5kI.VivLGbQM/i.4kbDGjxEaat4EJidcawe', 'active', CURRENT_TIMESTAMP);
INSERT INTO events (title, slug, category_id, venue_id, organizer_id, description, capacity, registration_opens_at, registration_closes_at, starts_at, ends_at, status) VALUES ('Конференция «Цифровые технологии в образовании»', 'digital-tech-conf-2026', 1, 1, 2, 'Доклады преподавателей и студентов о цифровых инструментах в учебном процессе, работе с данными и веб-разработке.', 120, '2026-10-01 09:00:00', '2026-11-19 23:59:00', '2026-11-20 10:00:00', '2026-11-20 16:00:00', 'published');
INSERT INTO events (title, slug, category_id, venue_id, organizer_id, description, capacity, registration_opens_at, registration_closes_at, starts_at, ends_at, status) VALUES ('Практический семинар по SQL Server', 'sql-server-seminar-2026', 2, 2, 2, 'Разбор запросов, индексов и резервного копирования на практических примерах. Нужен ноутбук.', 30, '2026-10-01 09:00:00', '2026-11-27 23:59:00', '2026-11-28 12:00:00', '2026-11-28 14:00:00', 'published');
INSERT INTO events (title, slug, category_id, venue_id, organizer_id, description, capacity, registration_opens_at, registration_closes_at, starts_at, ends_at, status) VALUES ('Хакатон «Код города»', 'city-code-hackathon-2026', 4, 3, 2, 'Команды за два дня разрабатывают прототип сервиса для колледжа и защищают результат перед жюри.', 60, '2026-10-01 09:00:00', '2026-12-04 23:59:00', '2026-12-05 09:00:00', '2026-12-06 18:00:00', 'published');
INSERT INTO events (title, slug, category_id, venue_id, organizer_id, description, capacity, registration_opens_at, registration_closes_at, starts_at, ends_at, status) VALUES ('Олимпиада по программированию', 'programming-olympiad-2026', 3, 2, 2, 'Индивидуальная олимпиада для студентов. Количество мест специально ограничено для проверки листа ожидания.', 1, '2026-10-01 09:00:00', '2026-12-11 23:59:00', '2026-12-12 10:00:00', '2026-12-12 13:00:00', 'published');
INSERT INTO events (title, slug, category_id, venue_id, organizer_id, description, capacity, registration_opens_at, registration_closes_at, starts_at, ends_at, status) VALUES ('День открытых дверей ИМСИТ', 'open-day-2026', 1, 1, 2, 'Встреча абитуриентов с преподавателями, экскурсия по корпусам и ответы на вопросы.', 200, '2026-08-01 09:00:00', '2026-09-17 23:59:00', '2026-09-18 11:00:00', '2026-09-18 14:00:00', 'completed');
INSERT INTO events (title, slug, category_id, venue_id, organizer_id, description, capacity, registration_opens_at, registration_closes_at, starts_at, ends_at, status) VALUES ('Мастер-класс по веб-дизайну', 'web-design-masterclass-2027', 2, 3, 2, 'Типографика, сетка и прототипирование интерфейса. Мероприятие ожидает модерации администратором.', 25, '2026-12-01 09:00:00', '2027-01-21 23:59:00', '2027-01-22 13:00:00', '2027-01-22 15:00:00', 'moderation');
INSERT INTO event_program (event_id, title, speaker, place, starts_at, ends_at, sort_order) VALUES (1, 'Открытие конференции', 'Волкова А.С.', 'Актовый зал', '2026-11-20 10:00:00', '2026-11-20 10:30:00', 1);
INSERT INTO event_program (event_id, title, speaker, place, starts_at, ends_at, sort_order) VALUES (1, 'Базы данных в учебных проектах', 'Преподаватель колледжа', 'Актовый зал', '2026-11-20 10:30:00', '2026-11-20 11:30:00', 2);
INSERT INTO event_program (event_id, title, speaker, place, starts_at, ends_at, sort_order) VALUES (1, 'Студенческие доклады и обсуждение', NULL, 'Актовый зал', '2026-11-20 12:00:00', '2026-11-20 15:30:00', 3);
INSERT INTO event_program (event_id, title, speaker, place, starts_at, ends_at, sort_order) VALUES (3, 'Формирование команд и постановка задач', 'Оргкомитет', 'IT-лаборатория', '2026-12-05 09:00:00', '2026-12-05 10:00:00', 1);
INSERT INTO event_program (event_id, title, speaker, place, starts_at, ends_at, sort_order) VALUES (3, 'Защита проектов', 'Жюри хакатона', 'IT-лаборатория', '2026-12-06 15:00:00', '2026-12-06 18:00:00', 2);
INSERT INTO registrations (event_id, user_id, status, qr_token) VALUES (1, 3, 'registered', 'a0123456789abcdef0123456789abcde');
INSERT INTO registrations (event_id, user_id, status, qr_token) VALUES (4, 3, 'confirmed', 'b0123456789abcdef0123456789abcde');
INSERT INTO registrations (event_id, user_id, status, qr_token, attended_at) VALUES (5, 3, 'attended', 'c0123456789abcdef0123456789abcde', '2026-09-18 11:05:00');
INSERT INTO notifications (user_id, event_id, type, channel, subject, message, status, is_read, sent_at) VALUES (3, 1, 'registration', 'web', 'Запись подтверждена', 'Вы записаны на конференцию «Цифровые технологии в образовании». Билет находится в личном кабинете.', 'sent', 0, CURRENT_TIMESTAMP);
