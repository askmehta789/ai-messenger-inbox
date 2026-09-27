-- AI Messenger Inbox — MariaDB / MySQL (InnoDB, utf8mb4 for Nepali + emoji)
-- Import once via phpMyAdmin or: mysql -u USER -p DBNAME < schema.sql

CREATE TABLE IF NOT EXISTS businesses (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                 VARCHAR(191) NOT NULL,
  ai_provider          VARCHAR(32)  NULL,               -- 'openai' | 'anthropic' | ...
  ai_api_key_encrypted TEXT         NULL,                -- sodium_crypto_secretbox, base64
  ai_model             VARCHAR(64)  NULL,
  instructions         TEXT         NULL,               -- free-text business context for the AI's system prompt
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id    BIGINT UNSIGNED NOT NULL,
  page_id        VARCHAR(32)  NOT NULL,
  page_name      VARCHAR(191) NULL,
  access_token   TEXT         NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_page (page_id),
  KEY idx_business (business_id),
  CONSTRAINT fk_pages_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id     BIGINT UNSIGNED NOT NULL,
  page_id         VARCHAR(32)  NOT NULL,
  psid            VARCHAR(40)  NOT NULL,
  customer_name   VARCHAR(191) NULL,
  lang            CHAR(2)      NOT NULL DEFAULT 'ne',
  human_until     INT UNSIGNED NULL,                    -- AI silent until this unix time (manual takeover)
  needs_human     TINYINT(1)   NOT NULL DEFAULT 0,
  last_message    TEXT         NULL,
  first_seen      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_conv (page_id, psid),
  KEY idx_business_seen (business_id, last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id     BIGINT UNSIGNED NOT NULL,
  page_id         VARCHAR(32)  NOT NULL,
  psid            VARCHAR(40)  NOT NULL,
  name            VARCHAR(191) NULL,
  phone           VARCHAR(32)  NULL,
  address         VARCHAR(255) NULL,
  product         VARCHAR(255) NULL,
  status          ENUM('collecting','new','called','ordered','not_interested','invalid') NOT NULL DEFAULT 'collecting',
  notes           TEXT         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lead (page_id, psid),
  KEY idx_business_status (business_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id     BIGINT UNSIGNED NOT NULL,
  page_id         VARCHAR(32)  NOT NULL,
  psid            VARCHAR(40)  NOT NULL,
  direction       ENUM('in','out','echo','agent') NOT NULL,
  mid             VARCHAR(191) NULL,
  body            TEXT         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mid (mid),
  KEY idx_conv (page_id, psid, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
