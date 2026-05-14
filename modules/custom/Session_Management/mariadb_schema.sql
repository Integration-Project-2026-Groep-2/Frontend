-- MariaDB Schema for Frontend Service (Lowercase Version)
-- Converted from PostgreSQL schema.sql and normalized to lowercase

-- Drop existing tables
DROP TABLE IF EXISTS {session_speaker};
DROP TABLE IF EXISTS {registration};
DROP TABLE IF EXISTS {Registration};
DROP TABLE IF EXISTS {session_change_log};
DROP TABLE IF EXISTS {session};
DROP TABLE IF EXISTS {location};
DROP TABLE IF EXISTS {speaker};
DROP TABLE IF EXISTS {participant};
DROP TABLE IF EXISTS {processed_messages};
DROP TABLE IF EXISTS {frontend_user};

CREATE TABLE IF NOT EXISTS {location} (
    location_id VARCHAR(36)  PRIMARY KEY,
    room_name   VARCHAR(100) NOT NULL UNIQUE,
    address     VARCHAR(255),
    capacity    INT          NOT NULL,
    status      VARCHAR(50)  NOT NULL DEFAULT 'beschikbaar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {speaker} (
    speaker_id    VARCHAR(36)  PRIMARY KEY,
    crm_master_id VARCHAR(36),
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    phone_number  VARCHAR(20),
    company       VARCHAR(255),
    is_active     BOOLEAN      NOT NULL DEFAULT true,
    gdpr_consent  BOOLEAN      NOT NULL DEFAULT false
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {session} (
    session_id       VARCHAR(36)  PRIMARY KEY,
    title            VARCHAR(255) NOT NULL,
    description      TEXT,
    date             DATE         NOT NULL,
    start_time       TIME         NOT NULL,
    end_time         TIME         NOT NULL,
    status           VARCHAR(50)  NOT NULL DEFAULT 'concept',
    location_id      VARCHAR(36),
    capacity         INT          NOT NULL,
    sync_status      VARCHAR(50)  NOT NULL DEFAULT 'pending',
    outlook_event_id VARCHAR(255),
    FOREIGN KEY (location_id) REFERENCES {location}(location_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {session_speaker} (
    session_speaker_id VARCHAR(36)  PRIMARY KEY,
    session_id         VARCHAR(36)  NOT NULL,
    speaker_id         VARCHAR(36)  NOT NULL,
    role               VARCHAR(100),
    confirmed          BOOLEAN      NOT NULL DEFAULT false,
    FOREIGN KEY (session_id) REFERENCES {session}(session_id) ON DELETE CASCADE,
    FOREIGN KEY (speaker_id) REFERENCES {speaker}(speaker_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {participant} (
    participant_id VARCHAR(36)  PRIMARY KEY,
    first_name     VARCHAR(100) NOT NULL,
    last_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(255) NOT NULL,
    company        VARCHAR(255),
    crm_master_id  VARCHAR(36),
    gdpr_consent   BOOLEAN      NOT NULL DEFAULT false
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {registration} (
    registration_id   VARCHAR(36)  PRIMARY KEY,
    session_id        VARCHAR(36)  NOT NULL,
    participant_id    VARCHAR(36)  NOT NULL,
    crm_master_id     VARCHAR(36),
    registration_time TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active         BOOLEAN      NOT NULL DEFAULT true,
    FOREIGN KEY (session_id) REFERENCES {session}(session_id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES {participant}(participant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {session_change_log} (
    log_id         VARCHAR(36)  PRIMARY KEY,
    session_id     VARCHAR(36)  NOT NULL,
    old_start_time DATETIME,
    new_start_time DATETIME,
    old_end_time   DATETIME,
    new_end_time   DATETIME,
    reason         TEXT,
    changed_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by     VARCHAR(255),
    FOREIGN KEY (session_id) REFERENCES {session}(session_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {processed_messages} (
    message_id   VARCHAR(255) PRIMARY KEY,
    processed_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE {frontend_user} (
    user_id       VARCHAR(36)  PRIMARY KEY,
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    role          VARCHAR(50)  NOT NULL,
    company       VARCHAR(255),
    is_active     BOOLEAN      NOT NULL DEFAULT true,
    crm_master_id VARCHAR(36)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
