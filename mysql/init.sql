CREATE DATABASE IF NOT EXISTS gamedb;
USE gamedb;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    api_token     VARCHAR(64)  DEFAULT NULL,
    last_login    DATETIME     DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS sessions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT         NOT NULL,
    session_uuid     VARCHAR(36) NOT NULL UNIQUE,
    started_at       DATETIME    NOT NULL DEFAULT NOW(),
    ended_at         DATETIME    DEFAULT NULL,
    duration_seconds INT         DEFAULT NULL,
    CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS stats (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT         NOT NULL,
    session_id      INT         NOT NULL,
    score           INT         NOT NULL DEFAULT 0,
    shots_or_slashes INT        NOT NULL DEFAULT 0,
    result          ENUM('win','loss','draw') NOT NULL,
    recorded_at     DATETIME    NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_stats_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_stats_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
);
