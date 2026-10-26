IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = N'prestige_events')
BEGIN
CREATE DATABASE [prestige_events] COLLATE Cyrillic_General_100_CI_AI_SC_UTF8;
END
GO
USE [prestige_events];
GO
SET NOCOUNT ON;
GO
IF OBJECT_ID(N'dbo.action_log', N'U') IS NOT NULL DROP TABLE dbo.action_log;
IF OBJECT_ID(N'dbo.notifications', N'U') IS NOT NULL DROP TABLE dbo.notifications;
IF OBJECT_ID(N'dbo.waitlist', N'U') IS NOT NULL DROP TABLE dbo.waitlist;
IF OBJECT_ID(N'dbo.registrations', N'U') IS NOT NULL DROP TABLE dbo.registrations;
IF OBJECT_ID(N'dbo.event_program', N'U') IS NOT NULL DROP TABLE dbo.event_program;
IF OBJECT_ID(N'dbo.events', N'U') IS NOT NULL DROP TABLE dbo.events;
IF OBJECT_ID(N'dbo.users', N'U') IS NOT NULL DROP TABLE dbo.users;
IF OBJECT_ID(N'dbo.venues', N'U') IS NOT NULL DROP TABLE dbo.venues;
IF OBJECT_ID(N'dbo.categories', N'U') IS NOT NULL DROP TABLE dbo.categories;
IF OBJECT_ID(N'dbo.settings', N'U') IS NOT NULL DROP TABLE dbo.settings;
IF OBJECT_ID(N'dbo.roles', N'U') IS NOT NULL DROP TABLE dbo.roles;
GO
CREATE TABLE dbo.roles (
id INT IDENTITY(1,1) NOT NULL,
code VARCHAR(32) NOT NULL,
name NVARCHAR(64) NOT NULL,
description NVARCHAR(255) NULL,
CONSTRAINT pk_roles PRIMARY KEY CLUSTERED (id),
CONSTRAINT uq_roles_code UNIQUE (code)
);
GO
CREATE TABLE dbo.categories (
id INT IDENTITY(1,1) NOT NULL,
name NVARCHAR(100) NOT NULL,
description NVARCHAR(500) NULL,
created_at DATETIME2(0) NOT NULL CONSTRAINT df_categories_created_at DEFAULT SYSDATETIME(),
CONSTRAINT pk_categories PRIMARY KEY CLUSTERED (id),
CONSTRAINT uq_categories_name UNIQUE (name)
);
GO
CREATE TABLE dbo.venues (
id INT IDENTITY(1,1) NOT NULL,
name NVARCHAR(150) NOT NULL,
address NVARCHAR(255) NOT NULL,
capacity INT NULL,
created_at DATETIME2(0) NOT NULL CONSTRAINT df_venues_created_at DEFAULT SYSDATETIME(),
CONSTRAINT pk_venues PRIMARY KEY CLUSTERED (id),
CONSTRAINT chk_venues_capacity CHECK (capacity IS NULL OR capacity > 0)
);
GO
CREATE TABLE dbo.settings (
setting_key VARCHAR(64) NOT NULL,
setting_value NVARCHAR(MAX) NOT NULL,
description NVARCHAR(255) NULL,
updated_at DATETIME2(0) NOT NULL CONSTRAINT df_settings_updated_at DEFAULT SYSDATETIME(),
CONSTRAINT pk_settings PRIMARY KEY CLUSTERED (setting_key)
);
GO
CREATE TABLE dbo.users (
id INT IDENTITY(1,1) NOT NULL,
role_id INT NOT NULL,
last_name NVARCHAR(64) NOT NULL,
first_name NVARCHAR(64) NOT NULL,
middle_name NVARCHAR(64) NULL,
email NVARCHAR(128) NOT NULL,
phone VARCHAR(20) NULL,
password_hash VARCHAR(255) NOT NULL,
status VARCHAR(20) NOT NULL CONSTRAINT df_users_status DEFAULT 'active',
email_verified_at DATETIME2(0) NULL,
created_at DATETIME2(0) NOT NULL CONSTRAINT df_users_created_at DEFAULT SYSDATETIME(),
updated_at DATETIME2(0) NOT NULL CONSTRAINT df_users_updated_at DEFAULT SYSDATETIME(),
CONSTRAINT pk_users PRIMARY KEY CLUSTERED (id),
CONSTRAINT uq_users_email UNIQUE (email),
CONSTRAINT fk_users_roles FOREIGN KEY (role_id) REFERENCES dbo.roles (id) ON DELETE NO ACTION ON UPDATE CASCADE,
CONSTRAINT chk_users_status CHECK (status IN ('active', 'blocked', 'pending'))
);
GO
CREATE TABLE dbo.events (
id INT IDENTITY(1,1) NOT NULL,
title NVARCHAR(200) NOT NULL,
slug VARCHAR(150) NOT NULL,
category_id INT NOT NULL,
venue_id INT NULL,
organizer_id INT NOT NULL,
description NVARCHAR(MAX) NULL,
capacity INT NULL,
registration_opens_at DATETIME2(0) NULL,
registration_closes_at DATETIME2(0) NULL,
starts_at DATETIME2(0) NOT NULL,
ends_at DATETIME2(0) NOT NULL,
status VARCHAR(20) NOT NULL CONSTRAINT df_events_status DEFAULT 'draft',
created_at DATETIME2(0) NOT NULL CONSTRAINT df_events_created_at DEFAULT SYSDATETIME(),
updated_at DATETIME2(0) NOT NULL CONSTRAINT df_events_updated_at DEFAULT SYSDATETIME(),
CONSTRAINT pk_events PRIMARY KEY CLUSTERED (id),
CONSTRAINT uq_events_slug UNIQUE (slug),
CONSTRAINT fk_events_categories FOREIGN KEY (category_id) REFERENCES dbo.categories (id) ON DELETE NO ACTION ON UPDATE CASCADE,
CONSTRAINT fk_events_venues FOREIGN KEY (venue_id) REFERENCES dbo.venues (id) ON DELETE SET NULL ON UPDATE CASCADE,
CONSTRAINT fk_events_organizer FOREIGN KEY (organizer_id) REFERENCES dbo.users (id) ON DELETE NO ACTION,
CONSTRAINT chk_events_capacity CHECK (capacity IS NULL OR capacity > 0),
CONSTRAINT chk_events_dates CHECK (ends_at >= starts_at),
CONSTRAINT chk_events_reg_dates CHECK (registration_opens_at IS NULL OR registration_closes_at IS NULL OR registration_closes_at >= registration_opens_at),
CONSTRAINT chk_events_status CHECK (status IN ('draft', 'moderation', 'published', 'cancelled', 'completed'))
);
GO
CREATE TABLE dbo.event_program (
id INT IDENTITY(1,1) NOT NULL,
event_id INT NOT NULL,
title NVARCHAR(200) NOT NULL,
speaker NVARCHAR(150) NULL,
place NVARCHAR(100) NULL,
starts_at DATETIME2(0) NOT NULL,
ends_at DATETIME2(0) NOT NULL,
sort_order INT NOT NULL CONSTRAINT df_event_program_sort DEFAULT 0,
CONSTRAINT pk_event_program PRIMARY KEY CLUSTERED (id),
CONSTRAINT fk_event_program_events FOREIGN KEY (event_id) REFERENCES dbo.events (id) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT chk_event_program_dates CHECK (ends_at >= starts_at)
);
GO
CREATE TABLE dbo.registrations (
id INT IDENTITY(1,1) NOT NULL,
event_id INT NOT NULL,
user_id INT NOT NULL,
status VARCHAR(20) NOT NULL CONSTRAINT df_registrations_status DEFAULT 'registered',
qr_token VARCHAR(64) NOT NULL,
attended_at DATETIME2(0) NULL,
registered_at DATETIME2(0) NOT NULL CONSTRAINT df_registrations_registered_at DEFAULT SYSDATETIME(),
cancelled_at DATETIME2(0) NULL,
CONSTRAINT pk_registrations PRIMARY KEY CLUSTERED (id),
CONSTRAINT uq_registrations_event_user UNIQUE (event_id, user_id),
CONSTRAINT uq_registrations_qr_token UNIQUE (qr_token),
CONSTRAINT fk_registrations_events FOREIGN KEY (event_id) REFERENCES dbo.events (id) ON DELETE CASCADE,
CONSTRAINT fk_registrations_users FOREIGN KEY (user_id) REFERENCES dbo.users (id) ON DELETE CASCADE,
CONSTRAINT chk_registrations_status CHECK (status IN ('registered', 'confirmed', 'attended', 'cancelled'))
);
GO
CREATE TABLE dbo.waitlist (
id INT IDENTITY(1,1) NOT NULL,
event_id INT NOT NULL,
user_id INT NOT NULL,
position INT NOT NULL,
status VARCHAR(20) NOT NULL CONSTRAINT df_waitlist_status DEFAULT 'waiting',
created_at DATETIME2(0) NOT NULL CONSTRAINT df_waitlist_created_at DEFAULT SYSDATETIME(),
updated_at DATETIME2(0) NOT NULL CONSTRAINT df_waitlist_updated_at DEFAULT SYSDATETIME(),
CONSTRAINT pk_waitlist PRIMARY KEY CLUSTERED (id),
CONSTRAINT uq_waitlist_event_user UNIQUE (event_id, user_id),
CONSTRAINT fk_waitlist_events FOREIGN KEY (event_id) REFERENCES dbo.events (id) ON DELETE CASCADE,
CONSTRAINT fk_waitlist_users FOREIGN KEY (user_id) REFERENCES dbo.users (id) ON DELETE CASCADE,
CONSTRAINT chk_waitlist_position CHECK (position > 0),
CONSTRAINT chk_waitlist_status CHECK (status IN ('waiting', 'invited', 'registered', 'rejected'))
);
GO
CREATE TABLE dbo.notifications (
id INT IDENTITY(1,1) NOT NULL,
user_id INT NOT NULL,
event_id INT NULL,
type VARCHAR(32) NOT NULL,
channel VARCHAR(20) NOT NULL,
subject NVARCHAR(200) NOT NULL,
message NVARCHAR(MAX) NOT NULL,
status VARCHAR(20) NOT NULL CONSTRAINT df_notifications_status DEFAULT 'pending',
is_read BIT NOT NULL CONSTRAINT df_notifications_is_read DEFAULT 0,
created_at DATETIME2(0) NOT NULL CONSTRAINT df_notifications_created_at DEFAULT SYSDATETIME(),
sent_at DATETIME2(0) NULL,
CONSTRAINT pk_notifications PRIMARY KEY CLUSTERED (id),
CONSTRAINT fk_notifications_users FOREIGN KEY (user_id) REFERENCES dbo.users (id) ON DELETE CASCADE,
CONSTRAINT fk_notifications_events FOREIGN KEY (event_id) REFERENCES dbo.events (id) ON DELETE SET NULL,
CONSTRAINT chk_notifications_channel CHECK (channel IN ('email', 'web')),
CONSTRAINT chk_notifications_status CHECK (status IN ('pending', 'sent', 'failed'))
);
GO
CREATE TABLE dbo.action_log (
id BIGINT IDENTITY(1,1) NOT NULL,
user_id INT NULL,
action VARCHAR(64) NOT NULL,
entity VARCHAR(64) NOT NULL,
entity_id INT NULL,
details NVARCHAR(MAX) NULL,
ip_address VARCHAR(45) NULL,
user_agent NVARCHAR(500) NULL,
created_at DATETIME2(0) NOT NULL CONSTRAINT df_action_log_created_at DEFAULT SYSDATETIME(),
CONSTRAINT pk_action_log PRIMARY KEY CLUSTERED (id),
CONSTRAINT fk_action_log_users FOREIGN KEY (user_id) REFERENCES dbo.users (id) ON DELETE SET NULL,
CONSTRAINT chk_action_log_details_json CHECK (details IS NULL OR ISJSON(details) = 1)
);
GO
CREATE NONCLUSTERED INDEX idx_events_status_dates ON dbo.events (status, starts_at) INCLUDE (title, slug, category_id, venue_id, capacity);
CREATE NONCLUSTERED INDEX idx_events_category ON dbo.events (category_id);
CREATE NONCLUSTERED INDEX idx_events_venue ON dbo.events (venue_id);
CREATE NONCLUSTERED INDEX idx_event_program_event_sort ON dbo.event_program (event_id, sort_order);
CREATE NONCLUSTERED INDEX idx_notifications_user_unread ON dbo.notifications (user_id, is_read) INCLUDE (subject, created_at);
CREATE NONCLUSTERED INDEX idx_action_log_created_at ON dbo.action_log (created_at DESC);
CREATE NONCLUSTERED INDEX idx_registrations_event_status ON dbo.registrations (event_id, status);
GO
INSERT INTO dbo.roles (code, name, description) VALUES
('guest', N'Гость', N'Неавторизованный посетитель: просмотр афиши и программы'),
('participant', N'Участник', N'Запись на мероприятия, получение билетов с QR, участие в листе ожидания'),
('organizer', N'Организатор', N'Создание и ведение мероприятий, учет участников, отметка посещения'),
('admin', N'Администратор', N'Полный контроль системы, справочников, аудит и сводные отчеты');
INSERT INTO dbo.categories (name, description) VALUES
(N'Конференция', N'Научно-практические и образовательные конференции'),
(N'Семинар', N'Обучающие семинары и практические мастер-классы'),
(N'Олимпиада', N'Студенческие олимпиады и конкурсы профессионального мастерства'),
(N'Хакатон', N'Командные соревнования по разработке программных решений');
INSERT INTO dbo.venues (name, address, capacity) VALUES
(N'Главный корпус, актовый зал', N'г. Краснодар, ул. Зиповская, 5', 250),
(N'Корпус 2, аудитория 305', N'г. Краснодар, ул. Зиповская, 5', 40),
(N'IT-лаборатория', N'г. Краснодар, ул. Зиповская, 5', 60);
INSERT INTO dbo.settings (setting_key, setting_value, description) VALUES
('platform_title', N'Prestige Events', N'Название платформы'),
('max_waitlist_size', N'50', N'Максимальный размер листа ожидания на одно событие'),
('qr_token_salt', N'PrestigeSecretKey2026', N'Соль для криптографической генерации токена QR'),
('email_sender_name', N'Оргкомитет Prestige Events', N'Имя отправителя в почтовых уведомлениях');
INSERT INTO dbo.users (role_id, last_name, first_name, middle_name, email, phone, password_hash, status, email_verified_at) VALUES
(4, N'Администратор', N'Системный', NULL, 'admin@prestige.local', '+7 900 000-00-01', '$2y$10$RFMVLGFfgrEPHFUO3hxR1OMCJppelRgDhWglWaouLo0cH6mQxVcjy', 'active', SYSDATETIME()),
(3, N'Волкова', N'Анна', N'Сергеевна', 'organizer@prestige.local', '+7 900 000-00-02', '$2y$10$becBr6El2tK0XNQbc4tFeuga7p9OicHgB1tqO8nb3l5tTeeuwSr6m', 'active', SYSDATETIME()),
(2, N'Студентов', N'Иван', N'Петрович', 'student@prestige.local', '+7 900 000-00-03', '$2y$10$.jp.egUjv18Htj4HGS5kI.VivLGbQM/i.4kbDGjxEaat4EJidcawe', 'active', SYSDATETIME());
INSERT INTO dbo.events (title, slug, category_id, venue_id, organizer_id, description, capacity, registration_opens_at, registration_closes_at, starts_at, ends_at, status) VALUES
(N'Конференция «Цифровые технологии в образовании»', 'digital-tech-conf-2026', 1, 1, 2, N'Доклады преподавателей и студентов о цифровых инструментах в учебном процессе, работе с данными и веб-разработке.', 120, '2026-10-01 09:00:00', '2026-11-19 23:59:00', '2026-11-20 10:00:00', '2026-11-20 16:00:00', 'published'),
(N'Практический семинар по SQL Server', 'sql-server-seminar-2026', 2, 2, 2, N'Разбор запросов, индексов и резервного копирования на практических примерах. Нужен ноутбук.', 30, '2026-10-01 09:00:00', '2026-11-27 23:59:00', '2026-11-28 12:00:00', '2026-11-28 14:00:00', 'published'),
(N'Хакатон «Код города»', 'city-code-hackathon-2026', 4, 3, 2, N'Команды за два дня разрабатывают прототип сервиса для колледжа и защищают результат перед жюри.', 60, '2026-10-01 09:00:00', '2026-12-04 23:59:00', '2026-12-05 09:00:00', '2026-12-06 18:00:00', 'published'),
(N'Олимпиада по программированию', 'programming-olympiad-2026', 3, 2, 2, N'Индивидуальная олимпиада для студентов. Количество мест специально ограничено для проверки листа ожидания.', 1, '2026-10-01 09:00:00', '2026-12-11 23:59:00', '2026-12-12 10:00:00', '2026-12-12 13:00:00', 'published'),
(N'День открытых дверей ИМСИТ', 'open-day-2026', 1, 1, 2, N'Встреча абитуриентов с преподавателями, экскурсия по корпусам и ответы на вопросы.', 200, '2026-08-01 09:00:00', '2026-09-17 23:59:00', '2026-09-18 11:00:00', '2026-09-18 14:00:00', 'completed'),
(N'Мастер-класс по веб-дизайну', 'web-design-masterclass-2027', 2, 3, 2, N'Типографика, сетка и прототипирование интерфейса. Мероприятие ожидает модерации администратором.', 25, '2026-12-01 09:00:00', '2027-01-21 23:59:00', '2027-01-22 13:00:00', '2027-01-22 15:00:00', 'moderation');
INSERT INTO dbo.event_program (event_id, title, speaker, place, starts_at, ends_at, sort_order) VALUES
(1, N'Открытие конференции', N'Волкова А.С.', N'Актовый зал', '2026-11-20 10:00:00', '2026-11-20 10:30:00', 1),
(1, N'Базы данных в учебных проектах', N'Преподаватель колледжа', N'Актовый зал', '2026-11-20 10:30:00', '2026-11-20 11:30:00', 2),
(1, N'Студенческие доклады и обсуждение', NULL, N'Актовый зал', '2026-11-20 12:00:00', '2026-11-20 15:30:00', 3),
(3, N'Формирование команд и постановка задач', N'Оргкомитет', N'IT-лаборатория', '2026-12-05 09:00:00', '2026-12-05 10:00:00', 1),
(3, N'Защита проектов', N'Жюри хакатона', N'IT-лаборатория', '2026-12-06 15:00:00', '2026-12-06 18:00:00', 2);
INSERT INTO dbo.registrations (event_id, user_id, status, qr_token) VALUES (1, 3, 'registered', 'a0123456789abcdef0123456789abcde');
INSERT INTO dbo.registrations (event_id, user_id, status, qr_token) VALUES (4, 3, 'confirmed', 'b0123456789abcdef0123456789abcde');
INSERT INTO dbo.registrations (event_id, user_id, status, qr_token, attended_at) VALUES (5, 3, 'attended', 'c0123456789abcdef0123456789abcde', '2026-09-18 11:05:00');
INSERT INTO dbo.notifications (user_id, event_id, type, channel, subject, message, status, is_read, sent_at) VALUES (3, 1, 'registration', 'web', N'Запись подтверждена', N'Вы записаны на конференцию «Цифровые технологии в образовании». Билет находится в личном кабинете.', 'sent', 0, SYSDATETIME());
GO
