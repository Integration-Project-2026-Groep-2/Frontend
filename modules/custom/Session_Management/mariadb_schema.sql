-- MariaDB Schema for Frontend Service
-- Converted from PostgreSQL schema.sql

CREATE TABLE IF NOT EXISTS {Location} (
    locationId  VARCHAR(36)  PRIMARY KEY,
    roomName    VARCHAR(100) NOT NULL UNIQUE,
    address     VARCHAR(255),
    capacity    INT          NOT NULL,
    status      VARCHAR(50)  NOT NULL DEFAULT 'beschikbaar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {Speaker} (
    speakerId   VARCHAR(36)  PRIMARY KEY,
    crmMasterId VARCHAR(36),
    firstName   VARCHAR(100) NOT NULL,
    lastName    VARCHAR(100) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    phoneNumber VARCHAR(20),
    company     VARCHAR(255),
    isActive    BOOLEAN      NOT NULL DEFAULT true,
    gdprConsent BOOLEAN      NOT NULL DEFAULT false
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {Session} (
    sessionId      VARCHAR(36)  PRIMARY KEY,
    title          VARCHAR(255) NOT NULL,
    description    TEXT,
    date           DATE         NOT NULL,
    startTime      TIME         NOT NULL,
    endTime        TIME         NOT NULL,
    status         VARCHAR(50)  NOT NULL DEFAULT 'concept',
    locationId     VARCHAR(36),
    capacity       INT          NOT NULL,
    syncStatus     VARCHAR(50)  NOT NULL DEFAULT 'pending',
    outlookEventId VARCHAR(255),
    FOREIGN KEY (locationId) REFERENCES {Location}(locationId) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {SessionSpeaker} (
    sessionSpeakerId VARCHAR(36)  PRIMARY KEY,
    sessionId        VARCHAR(36)  NOT NULL,
    speakerId        VARCHAR(36)  NOT NULL,
    role             VARCHAR(100),
    confirmed        BOOLEAN      NOT NULL DEFAULT false,
    FOREIGN KEY (sessionId) REFERENCES {Session}(sessionId) ON DELETE CASCADE,
    FOREIGN KEY (speakerId) REFERENCES {Speaker}(speakerId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {Participant} (
    participantId VARCHAR(36)  PRIMARY KEY,
    firstName     VARCHAR(100) NOT NULL,
    lastName      VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    company       VARCHAR(255),
    crmMasterId   VARCHAR(36),
    gdprConsent   BOOLEAN      NOT NULL DEFAULT false
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {Registration} (
    registrationId   VARCHAR(36)  PRIMARY KEY,
    sessionId        VARCHAR(36)  NOT NULL,
    participantId    VARCHAR(36)  NOT NULL,
    crmMasterId      VARCHAR(36),
    registrationTime TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sessionId) REFERENCES {Session}(sessionId) ON DELETE CASCADE,
    FOREIGN KEY (participantId) REFERENCES {Participant}(participantId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {SessionChangeLog} (
    logId        VARCHAR(36)  PRIMARY KEY,
    sessionId    VARCHAR(36)  NOT NULL,
    oldStartTime DATETIME,
    newStartTime DATETIME,
    oldEndTime   DATETIME,
    newEndTime   DATETIME,
    reason       TEXT,
    changedAt    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changedBy    VARCHAR(255),
    FOREIGN KEY (sessionId) REFERENCES {Session}(sessionId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {ProcessedMessages} (
    messageId   VARCHAR(255) PRIMARY KEY,
    processedAt TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS {FrontendUser} (
    userId      VARCHAR(36)  PRIMARY KEY,
    firstName   VARCHAR(100) NOT NULL,
    lastName    VARCHAR(100) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    role        VARCHAR(50)  NOT NULL,
    company     VARCHAR(255),
    isActive    BOOLEAN      NOT NULL DEFAULT true,
    crmMasterId VARCHAR(36)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

