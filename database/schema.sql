-- ═══════════════════════════════════════════════════════════════════════════
-- SportsMIS® Regatta — full schema for a fresh install.
--
-- A running deployment does NOT need this file: every table and column is
-- also created by the idempotent Models\Schema::ensureX() migrations, which
-- controllers call from their boot(). This dump exists so a new database can
-- be created in one step, and so the structure is reviewable in one place.
--
--   mysql -u USER -p sportsmis_regatta < database/schema.sql
--   mysql -u USER -p sportsmis_regatta < database/seeds.sql   -- optional
--
-- Keep this file in step with app/models/Schema.php.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Platform accounts ──────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('super_admin') NOT NULL DEFAULT 'super_admin',
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Events (the tenant boundary) ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS events (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(20) NULL COMMENT 'Event Code used at event-admin / event-user sign-in',
    name           VARCHAR(200) NOT NULL,
    name_regional  VARCHAR(200) NULL,
    image          VARCHAR(255) NULL,
    venue          VARCHAR(200) NULL,
    district       VARCHAR(120) NULL,
    organiser      VARCHAR(200) NULL,
    description    TEXT NULL,
    start_date     DATE NULL,
    end_date       DATE NULL,
    status         ENUM('draft','active','completed','archived') NOT NULL DEFAULT 'draft',
    default_lanes  TINYINT UNSIGNED NOT NULL DEFAULT 6,
    chroma_color   VARCHAR(20) NOT NULL DEFAULT '#00b140',
    display_pin    VARCHAR(12) NULL,
    slide_seconds  SMALLINT UNSIGNED NOT NULL DEFAULT 9,
    created_by     INT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_events_code (code),
    KEY idx_events_status (status),
    KEY idx_events_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Per-event logins ───────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS event_admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event_id      INT NOT NULL,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    phone         VARCHAR(20) NULL,
    password      VARCHAR(255) NOT NULL,
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_admin (event_id, email),
    KEY idx_event_admin_event (event_id),
    CONSTRAINT fk_event_admin_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event_id      INT NOT NULL,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    phone         VARCHAR(20) NULL,
    designation   VARCHAR(120) NULL,
    password      VARCHAR(255) NOT NULL,
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_user (event_id, email),
    KEY idx_event_user_event (event_id),
    CONSTRAINT fk_event_user_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Privilege names come from Models\EventUser::PRIVILEGES:
--   rounds_heats · lane_allocation · result_entry · reports · displays
CREATE TABLE IF NOT EXISTS event_user_privileges (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event_user_id INT NOT NULL,
    privilege     VARCHAR(60) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_user_privilege (event_user_id, privilege),
    CONSTRAINT fk_eup_user FOREIGN KEY (event_user_id) REFERENCES event_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Teams and registrations ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS teams (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event_id      INT NOT NULL,
    club_name     VARCHAR(200) NOT NULL,
    boat_name     VARCHAR(200) NOT NULL,
    captain_name  VARCHAR(150) NOT NULL,
    boat_class    VARCHAR(120) NULL,
    home_place    VARCHAR(150) NULL,
    contact_name  VARCHAR(150) NULL,
    contact_phone VARCHAR(20) NULL,
    contact_email VARCHAR(190) NULL,
    logo          VARCHAR(255) NULL,
    short_code    VARCHAR(20) NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_teams_event (event_id),
    KEY idx_teams_club (event_id, club_name),
    CONSTRAINT fk_teams_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_registrations (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    event_id     INT NOT NULL,
    team_id      INT NOT NULL,
    status       ENUM('draft','submitted','approved','returned') NOT NULL DEFAULT 'draft',
    remarks      VARCHAR(500) NULL,
    submitted_at DATETIME NULL,
    decided_at   DATETIME NULL,
    decided_by   VARCHAR(150) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_registration (event_id, team_id),
    KEY idx_treg_status (event_id, status),
    CONSTRAINT fk_treg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_treg_team  FOREIGN KEY (team_id)  REFERENCES teams(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Programme (Order of Events) ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS event_races (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event_id      INT NOT NULL,
    sl_no         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    code          VARCHAR(30) NULL,
    name          VARCHAR(200) NOT NULL,
    name_regional VARCHAR(200) NULL,
    image         VARCHAR(255) NULL COMMENT 'picture on the public race card',
    boat_class    VARCHAR(120) NULL,
    category      VARCHAR(120) NULL,
    gender        ENUM('open','men','women','mixed') NOT NULL DEFAULT 'open',
    distance_m    SMALLINT UNSIGNED NULL,
    lane_count    TINYINT UNSIGNED NOT NULL DEFAULT 6,
    race_date     DATE NULL,
    race_time     TIME NULL,
    status        ENUM('scheduled','in_progress','finished','result_published','medal_ceremony')
                  NOT NULL DEFAULT 'scheduled',
    remarks       VARCHAR(500) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_race_event (event_id),
    KEY idx_race_order (event_id, race_date, race_time, sl_no),
    KEY idx_race_status (event_id, status),
    CONSTRAINT fk_race_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which approved boats contest which race.
CREATE TABLE IF NOT EXISTS race_entries (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    event_id             INT NOT NULL,
    event_race_id        INT NOT NULL,
    team_registration_id INT NOT NULL,
    image                VARCHAR(255) NULL COMMENT 'photo of this boat in this race; distinct from the club crest',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_race_entry (event_race_id, team_registration_id),
    KEY idx_race_entry_event (event_id),
    CONSTRAINT fk_entry_event FOREIGN KEY (event_id)      REFERENCES events(id)      ON DELETE CASCADE,
    CONSTRAINT fk_entry_race  FOREIGN KEY (event_race_id) REFERENCES event_races(id) ON DELETE CASCADE,
    CONSTRAINT fk_entry_treg  FOREIGN KEY (team_registration_id)
        REFERENCES team_registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Rounds → heats → lanes → results ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS rounds (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    event_id         INT NOT NULL,
    event_race_id    INT NOT NULL,
    name             VARCHAR(120) NOT NULL,
    round_type       ENUM('preliminary','quarter_final','semi_final','final','other')
                     NOT NULL DEFAULT 'preliminary',
    sort_order       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    lane_count       TINYINT UNSIGNED NOT NULL DEFAULT 6,
    qualify_per_heat TINYINT UNSIGNED NOT NULL DEFAULT 2,
    scheduled_date   DATE NULL COMMENT 'NULL inherits the race date',
    scheduled_time   TIME NULL COMMENT 'NULL inherits the race time',
    status           ENUM('draft','open','locked','published') NOT NULL DEFAULT 'draft',
    published_at     DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_round_race (event_race_id, sort_order),
    KEY idx_round_event (event_id),
    CONSTRAINT fk_round_event FOREIGN KEY (event_id)      REFERENCES events(id)      ON DELETE CASCADE,
    CONSTRAINT fk_round_race  FOREIGN KEY (event_race_id) REFERENCES event_races(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS heats (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    event_id       INT NOT NULL,
    round_id       INT NOT NULL,
    heat_no        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    name           VARCHAR(120) NULL,
    scheduled_date DATE NULL,
    scheduled_time TIME NULL,
    status         ENUM('scheduled','in_progress','finished','result_published')
                   NOT NULL DEFAULT 'scheduled',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_heat_no (round_id, heat_no),
    KEY idx_heat_event (event_id),
    CONSTRAINT fk_heat_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_heat_round FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One boat per lane, and one lane per boat within a round — both enforced
-- here so a double-tap on the drag handler cannot create a duplicate.
CREATE TABLE IF NOT EXISTS lane_allocations (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    event_id             INT NOT NULL,
    round_id             INT NOT NULL,
    heat_id              INT NOT NULL,
    lane_no              TINYINT UNSIGNED NOT NULL,
    team_registration_id INT NOT NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lane (heat_id, lane_no),
    UNIQUE KEY uq_round_team (round_id, team_registration_id),
    KEY idx_lane_event (event_id),
    CONSTRAINT fk_lane_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_lane_round FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE,
    CONSTRAINT fk_lane_heat  FOREIGN KEY (heat_id)  REFERENCES heats(id)  ON DELETE CASCADE,
    CONSTRAINT fk_lane_treg  FOREIGN KEY (team_registration_id)
        REFERENCES team_registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS results (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    event_id             INT NOT NULL,
    round_id             INT NOT NULL,
    heat_id              INT NOT NULL,
    lane_allocation_id   INT NOT NULL,
    team_registration_id INT NOT NULL,
    race_time            VARCHAR(20) NULL COMMENT 'MM:SS.mmm as normalised by normaliseRaceTime()',
    time_centis          INT UNSIGNED NULL COMMENT 'race_time in hundredths, for ordering',
    position             TINYINT UNSIGNED NULL,
    qualified            TINYINT(1) NOT NULL DEFAULT 0,
    outcome              ENUM('ok','dns','dnf','dsq') NOT NULL DEFAULT 'ok',
    remarks              VARCHAR(255) NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_result_allocation (lane_allocation_id),
    KEY idx_result_round (round_id),
    KEY idx_result_heat (heat_id),
    KEY idx_result_event (event_id),
    CONSTRAINT fk_result_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_result_round FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE,
    CONSTRAINT fk_result_heat  FOREIGN KEY (heat_id)  REFERENCES heats(id)  ON DELETE CASCADE,
    CONSTRAINT fk_result_lane  FOREIGN KEY (lane_allocation_id)
        REFERENCES lane_allocations(id) ON DELETE CASCADE,
    CONSTRAINT fk_result_treg  FOREIGN KEY (team_registration_id)
        REFERENCES team_registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Platform settings ──────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key   VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
