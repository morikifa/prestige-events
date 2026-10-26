CREATE DATABASE IF NOT EXISTS prestige_events
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE prestige_events;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS action_log;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS waitlist;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS event_program;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS venues;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
    id          INT AUTO_INCREMENT NOT NULL,
    code        VARCHAR(32) NOT NULL,
    name        VARCHAR(64) NOT NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_code (code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE categories (
    id          INT AUTO_INCREMENT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE venues (
    id         INT AUTO_INCREMENT NOT NULL,
    name       VARCHAR(150) NOT NULL,
    address    VARCHAR(255) NOT NULL,
    capacity   INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_venues_capacity CHECK (capacity IS NULL OR capacity > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE settings (
    setting_key   VARCHAR(64) NOT NULL,
    setting_value TEXT NOT NULL,
    description   VARCHAR(255) NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE users (
    id                INT AUTO_INCREMENT NOT NULL,
    role_id           INT NOT NULL,
    last_name         VARCHAR(64) NOT NULL,
    first_name        VARCHAR(64) NOT NULL,
    middle_name       VARCHAR(64) NULL,
    email             VARCHAR(128) NOT NULL,
    phone             VARCHAR(20) NULL,
    password_hash     VARCHAR(255) NOT NULL,
    status            VARCHAR(20) NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    CONSTRAINT fk_users_roles FOREIGN KEY (role_id) REFERENCES roles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_users_status CHECK (status IN ('active', 'blocked', 'pending'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE events (
    id                     INT AUTO_INCREMENT NOT NULL,
    title                  VARCHAR(200) NOT NULL,
    slug                   VARCHAR(150) NOT NULL,
    category_id            INT NOT NULL,
    venue_id               INT NULL,
    organizer_id           INT NOT NULL,
    description            TEXT NULL,
    capacity               INT NULL,
    registration_opens_at  DATETIME NULL,
    registration_closes_at DATETIME NULL,
    starts_at              DATETIME NOT NULL,
    ends_at                DATETIME NOT NULL,
    status                 VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_events_slug (slug),
    KEY idx_events_status_dates (status, starts_at, title, slug, category_id, venue_id, capacity),
    KEY idx_events_category (category_id),
    KEY idx_events_venue (venue_id),
    KEY idx_events_organizer (organizer_id),
    CONSTRAINT fk_events_categories FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_events_venues FOREIGN KEY (venue_id) REFERENCES venues (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_events_organizer FOREIGN KEY (organizer_id) REFERENCES users (id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_events_capacity CHECK (capacity IS NULL OR capacity > 0),
    CONSTRAINT chk_events_dates CHECK (ends_at >= starts_at),
    CONSTRAINT chk_events_reg_dates CHECK (
        registration_opens_at IS NULL OR
        registration_closes_at IS NULL OR
        registration_closes_at >= registration_opens_at
    ),
    CONSTRAINT chk_events_status CHECK (status IN ('draft', 'moderation', 'published', 'cancelled', 'completed'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE event_program (
    id         INT AUTO_INCREMENT NOT NULL,
    event_id   INT NOT NULL,
    title      VARCHAR(200) NOT NULL,
    speaker    VARCHAR(150) NULL,
    place      VARCHAR(100) NULL,
    starts_at  DATETIME NOT NULL,
    ends_at    DATETIME NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_event_program_event_sort (event_id, sort_order),
    CONSTRAINT fk_event_program_events FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_event_program_dates CHECK (ends_at >= starts_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE registrations (
    id            INT AUTO_INCREMENT NOT NULL,
    event_id      INT NOT NULL,
    user_id       INT NOT NULL,
    status        VARCHAR(20) NOT NULL DEFAULT 'registered',
    qr_token      VARCHAR(64) NOT NULL,
    attended_at   DATETIME NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_registrations_event_user (event_id, user_id),
    UNIQUE KEY uq_registrations_qr_token (qr_token),
    KEY idx_registrations_user (user_id),
    CONSTRAINT fk_registrations_events FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_registrations_users FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT chk_registrations_status CHECK (status IN ('registered', 'confirmed', 'attended', 'cancelled'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE waitlist (
    id         INT AUTO_INCREMENT NOT NULL,
    event_id   INT NOT NULL,
    user_id    INT NOT NULL,
    position   INT NOT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'waiting',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_waitlist_event_user (event_id, user_id),
    CONSTRAINT fk_waitlist_events FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_waitlist_users FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT chk_waitlist_position CHECK (position > 0),
    CONSTRAINT chk_waitlist_status CHECK (status IN ('waiting', 'invited', 'registered', 'rejected'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id         INT AUTO_INCREMENT NOT NULL,
    user_id    INT NOT NULL,
    event_id   INT NULL,
    type       VARCHAR(32) NOT NULL,
    channel    VARCHAR(20) NOT NULL,
    subject    VARCHAR(200) NOT NULL,
    message    TEXT NOT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'pending',
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_notifications_user_unread (user_id, is_read, subject, created_at),
    CONSTRAINT fk_notifications_users FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notifications_events FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE SET NULL,
    CONSTRAINT chk_notifications_channel CHECK (channel IN ('email', 'web')),
    CONSTRAINT chk_notifications_status CHECK (status IN ('pending', 'sent', 'failed'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE action_log (
    id         BIGINT AUTO_INCREMENT NOT NULL,
    user_id    INT NULL,
    action     VARCHAR(64) NOT NULL,
    entity     VARCHAR(64) NOT NULL,
    entity_id  INT NULL,
    details    TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_action_log_created_at (created_at),
    CONSTRAINT fk_action_log_users FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL,
    CONSTRAINT chk_action_log_details_json CHECK (details IS NULL OR JSON_VALID(details))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
