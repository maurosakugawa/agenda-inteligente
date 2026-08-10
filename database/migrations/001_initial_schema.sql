SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,

    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(254) NULL,
    cep VARCHAR(9) NULL,
    logradouro VARCHAR(190) NULL,
    numero VARCHAR(30) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_contacts_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE RESTRICT,

    KEY idx_contacts_user_name (
        user_id,
        name
    ),
    KEY idx_contacts_user_email (
        user_id,
        email
    ),
    KEY idx_contacts_user_phone (
        user_id,
        phone
    ),
    KEY idx_contacts_user_cidade (
        user_id,
        cidade
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    event_date DATE NOT NULL,
    event_time TIME NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'geral',
    priority VARCHAR(20) NOT NULL DEFAULT 'media',
    location VARCHAR(255) NULL,
    reminder_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_events_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE RESTRICT,

    KEY idx_events_user_date_time (
        user_id,
        event_date,
        event_time
    ),
    KEY idx_events_user_category (
        user_id,
        category
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_contacts (
    event_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,

    PRIMARY KEY (
        event_id,
        contact_id
    ),

    CONSTRAINT fk_event_contacts_event
        FOREIGN KEY (event_id)
        REFERENCES events (id)
        ON DELETE CASCADE,

    CONSTRAINT fk_event_contacts_contact
        FOREIGN KEY (contact_id)
        REFERENCES contacts (id)
        ON DELETE CASCADE,

    KEY idx_event_contacts_contact (
        contact_id
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE weather_cache (
    cache_key CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        PRIMARY KEY,
    cache_type VARCHAR(20) NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'openweather',
    schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    city_key VARCHAR(120) NOT NULL,
    city_query VARCHAR(120) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    fetched_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_weather_cache_expires_at (
        expires_at
    ),
    KEY idx_weather_cache_type_city (
        cache_type,
        city_key
    ),
    KEY idx_weather_cache_updated_at (
        updated_at
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
