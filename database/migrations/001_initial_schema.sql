SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_status (status),
    KEY idx_users_locked_until (locked_until)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(30) NULL,
    mobile_phone VARCHAR(30) NULL,
    company VARCHAR(150) NULL,
    postal_code VARCHAR(10) NULL,
    street VARCHAR(190) NULL,
    number VARCHAR(30) NULL,
    complement VARCHAR(100) NULL,
    district VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    state CHAR(2) NULL,
    birthday DATE NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_contacts_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE,

    KEY idx_contacts_user_name (user_id, name),
    KEY idx_contacts_user_email (user_id, email),
    KEY idx_contacts_user_phone (user_id, phone),
    KEY idx_contacts_deleted_at (deleted_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    location VARCHAR(255) NULL,
    category VARCHAR(50) NULL,
    priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    all_day TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('scheduled', 'completed', 'cancelled')
        NOT NULL DEFAULT 'scheduled',
    reminder_minutes SMALLINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,

    CONSTRAINT fk_events_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE,

    KEY idx_events_user_start (user_id, starts_at),
    KEY idx_events_user_status (user_id, status),
    KEY idx_events_deleted_at (deleted_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_participants (
    event_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NOT NULL,
    response_status ENUM('pending', 'accepted', 'declined', 'tentative')
        NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (event_id, contact_id),

    CONSTRAINT fk_event_participants_event
        FOREIGN KEY (event_id)
        REFERENCES events (id)
        ON DELETE CASCADE,

    CONSTRAINT fk_event_participants_contact
        FOREIGN KEY (contact_id)
        REFERENCES contacts (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE weather_cache (
    cache_key VARCHAR(190) PRIMARY KEY,
    response_json JSON NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_weather_cache_expires_at (expires_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schema_migrations (
    version VARCHAR(100) PRIMARY KEY,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version)
VALUES ('001_initial_schema');
