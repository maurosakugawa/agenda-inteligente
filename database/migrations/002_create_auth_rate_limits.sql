CREATE TABLE auth_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope VARCHAR(32)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,
    key_hash CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at DATETIME NOT NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uq_auth_rate_limits_scope_key (
        scope,
        key_hash
    ),

    KEY idx_auth_rate_limits_updated_at (
        updated_at
    ),

    KEY idx_auth_rate_limits_blocked_until (
        blocked_until
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
