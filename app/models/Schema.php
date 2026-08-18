<?php
namespace Models;

use Core\Model;

/**
 * Self-healing schema helper. Every ensure*() method is idempotent and safe
 * to call on each relevant request — it checks INFORMATION_SCHEMA before it
 * runs any CREATE / ALTER. Controllers call the ensure*() they depend on
 * from their private boot(), so a deployed database upgrades itself.
 *
 * database/schema.sql carries the same structure for a fresh install.
 */
class Schema extends Model
{
    /** Per-request memo so a method that runs in several controllers is cheap. */
    private static array $applied = [];

    // ── Platform accounts ────────────────────────────────────────────────────

    /** users: platform-level logins. Today only the super admin role. */
    public static function ensureUsers(): void
    {
        if (!empty(self::$applied['users'])) return;

        if (!self::tableExists('users')) {
            static::query("
                CREATE TABLE users (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // First-run bootstrap: without an account nobody can sign in at all.
        // The password is deliberately well-known and the UI nags until it is
        // changed — see AdminController::dashboard().
        $count = (int)static::value("SELECT COUNT(*) FROM users", [], 0);
        if ($count === 0) {
            static::query(
                "INSERT INTO users (name, email, password, role, status) VALUES (?,?,?,'super_admin','active')",
                ['Super Admin', 'admin@sportsmis.com', password_hash('ChangeMe@123', PASSWORD_BCRYPT, ['cost' => 12])]
            );
        }

        self::$applied['users'] = true;
    }

    // ── Events (tenants) ─────────────────────────────────────────────────────

    /** events: one row per regatta. `code` is the Event Code — the tenant's
     *  public handle, used to open a display screen, not to sign in. */
    public static function ensureEvents(): void
    {
        if (!empty(self::$applied['events'])) return;
        self::ensureUsers();

        if (!self::tableExists('events')) {
            static::query("
                CREATE TABLE events (
                    id             INT AUTO_INCREMENT PRIMARY KEY,
                    code           VARCHAR(20) NULL,
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Columns added after the first release land here.
        $cols = [
            'name_regional' => "ALTER TABLE events ADD COLUMN name_regional VARCHAR(200) NULL AFTER name",
            'image'         => "ALTER TABLE events ADD COLUMN image VARCHAR(255) NULL AFTER name_regional",
            'venue'         => "ALTER TABLE events ADD COLUMN venue VARCHAR(200) NULL AFTER image",
            'district'      => "ALTER TABLE events ADD COLUMN district VARCHAR(120) NULL AFTER venue",
            'organiser'     => "ALTER TABLE events ADD COLUMN organiser VARCHAR(200) NULL AFTER district",
            'description'   => "ALTER TABLE events ADD COLUMN description TEXT NULL AFTER organiser",
            'default_lanes' => "ALTER TABLE events ADD COLUMN default_lanes TINYINT UNSIGNED NOT NULL DEFAULT 6 AFTER status",
            'chroma_color'  => "ALTER TABLE events ADD COLUMN chroma_color VARCHAR(20) NOT NULL DEFAULT '#00b140' AFTER default_lanes",
            'display_pin'   => "ALTER TABLE events ADD COLUMN display_pin VARCHAR(12) NULL AFTER chroma_color",
            'slide_seconds' => "ALTER TABLE events ADD COLUMN slide_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 9 AFTER display_pin",
            'created_by'    => "ALTER TABLE events ADD COLUMN created_by INT NULL AFTER slide_seconds",
        ];
        foreach ($cols as $col => $ddl) {
            if (!self::columnExists('events', $col)) static::query($ddl);
        }
        if (!self::indexExists('events', 'uq_events_code')) {
            static::query("ALTER TABLE events ADD UNIQUE KEY uq_events_code (code)");
        }

        self::$applied['events'] = true;
    }

    // ── Per-event logins ─────────────────────────────────────────────────────

    /**
     * event_admins: the account an event administrator signs in with.
     * Unique per (event_id, email) so the same person can administer several
     * regattas with one address — which is why sign-in may have to ask which
     * one they meant.
     */
    public static function ensureEventAdmins(): void
    {
        if (!empty(self::$applied['event_admins'])) return;
        self::ensureEvents();

        if (!self::tableExists('event_admins')) {
            static::query("
                CREATE TABLE event_admins (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        self::$applied['event_admins'] = true;
    }

    /**
     * event_users (+ event_user_privileges): the privilege-gated race-office
     * accounts. Privilege names come from EventUser::PRIVILEGES.
     */
    public static function ensureEventUsers(): void
    {
        if (!empty(self::$applied['event_users'])) return;
        self::ensureEvents();

        if (!self::tableExists('event_users')) {
            static::query("
                CREATE TABLE event_users (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        if (!self::columnExists('event_users', 'designation')) {
            static::query("ALTER TABLE event_users ADD COLUMN designation VARCHAR(120) NULL AFTER phone");
        }

        if (!self::tableExists('event_user_privileges')) {
            static::query("
                CREATE TABLE event_user_privileges (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    event_user_id INT NOT NULL,
                    privilege     VARCHAR(60) NOT NULL,
                    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_event_user_privilege (event_user_id, privilege),
                    CONSTRAINT fk_eup_user FOREIGN KEY (event_user_id) REFERENCES event_users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        self::$applied['event_users'] = true;
    }

    // ── Teams and registrations ──────────────────────────────────────────────

    /**
     * teams: the event's club/boat master — one row per competing boat.
     * team_registrations: that boat's entry into the event, carrying the
     * draft -> submitted -> approved / returned workflow.
     */
    public static function ensureTeams(): void
    {
        if (!empty(self::$applied['teams'])) return;
        self::ensureEvents();

        if (!self::tableExists('teams')) {
            static::query("
                CREATE TABLE teams (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        foreach ([
            'boat_class' => "ALTER TABLE teams ADD COLUMN boat_class VARCHAR(120) NULL AFTER captain_name",
            'home_place' => "ALTER TABLE teams ADD COLUMN home_place VARCHAR(150) NULL AFTER boat_class",
            'short_code' => "ALTER TABLE teams ADD COLUMN short_code VARCHAR(20) NULL AFTER logo",
        ] as $col => $ddl) {
            if (!self::columnExists('teams', $col)) static::query($ddl);
        }

        if (!self::tableExists('team_registrations')) {
            static::query("
                CREATE TABLE team_registrations (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        self::$applied['teams'] = true;
    }

    // ── Programme (order of events) ──────────────────────────────────────────

    /**
     * event_races: the programme. One row per race item, carrying its serial
     * number, scheduled date/time and call-room status.
     */
    public static function ensureRaces(): void
    {
        if (!empty(self::$applied['races'])) return;
        self::ensureEvents();

        if (!self::tableExists('event_races')) {
            static::query("
                CREATE TABLE event_races (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    event_id      INT NOT NULL,
                    sl_no         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    code          VARCHAR(30) NULL,
                    name          VARCHAR(200) NOT NULL,
                    name_regional VARCHAR(200) NULL,
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // race_entries: which registered boats are contesting which race.
        if (!self::tableExists('race_entries')) {
            static::query("
                CREATE TABLE race_entries (
                    id                   INT AUTO_INCREMENT PRIMARY KEY,
                    event_id             INT NOT NULL,
                    event_race_id        INT NOT NULL,
                    team_registration_id INT NOT NULL,
                    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_race_entry (event_race_id, team_registration_id),
                    KEY idx_race_entry_event (event_id),
                    CONSTRAINT fk_entry_event FOREIGN KEY (event_id)      REFERENCES events(id)      ON DELETE CASCADE,
                    CONSTRAINT fk_entry_race  FOREIGN KEY (event_race_id) REFERENCES event_races(id) ON DELETE CASCADE,
                    CONSTRAINT fk_entry_treg  FOREIGN KEY (team_registration_id)
                        REFERENCES team_registrations(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // A boat's photo AS IT RACES HERE. Distinct from teams.logo (the club
        // crest): the same club may field a different boat in each race.
        if (!self::columnExists('race_entries', 'image')) {
            static::query("ALTER TABLE race_entries ADD COLUMN image VARCHAR(255) NULL AFTER team_registration_id");
        }

        self::$applied['races'] = true;
    }

    // ── Rounds, heats, lanes, results ────────────────────────────────────────

    /**
     * rounds -> heats -> lane_allocations -> results. Lane count is set per
     * round (a semi-final may run fewer lanes than the heats), and each heat
     * exposes exactly that many lanes.
     */
    public static function ensureRounds(): void
    {
        if (!empty(self::$applied['rounds'])) return;
        self::ensureRaces();
        self::ensureTeams();

        if (!self::tableExists('rounds')) {
            static::query("
                CREATE TABLE rounds (
                    id              INT AUTO_INCREMENT PRIMARY KEY,
                    event_id        INT NOT NULL,
                    event_race_id   INT NOT NULL,
                    name            VARCHAR(120) NOT NULL,
                    round_type      ENUM('preliminary','quarter_final','semi_final','final','other')
                                    NOT NULL DEFAULT 'preliminary',
                    sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 1,
                    lane_count      TINYINT UNSIGNED NOT NULL DEFAULT 6,
                    qualify_per_heat TINYINT UNSIGNED NOT NULL DEFAULT 2,
                    status          ENUM('draft','open','locked','published') NOT NULL DEFAULT 'draft',
                    published_at    DATETIME NULL,
                    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_round_race (event_race_id, sort_order),
                    KEY idx_round_event (event_id),
                    CONSTRAINT fk_round_event FOREIGN KEY (event_id)      REFERENCES events(id)      ON DELETE CASCADE,
                    CONSTRAINT fk_round_race  FOREIGN KEY (event_race_id) REFERENCES event_races(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // A round runs at its own time: prelims on the morning of day one,
        // semis and the final later or on another day. NULL means "inherit
        // the race's slot", so an event that runs everything in one block
        // needs no extra data entry.
        foreach ([
            'scheduled_date' => "ALTER TABLE rounds ADD COLUMN scheduled_date DATE NULL AFTER qualify_per_heat",
            'scheduled_time' => "ALTER TABLE rounds ADD COLUMN scheduled_time TIME NULL AFTER scheduled_date",
        ] as $col => $ddl) {
            if (!self::columnExists('rounds', $col)) static::query($ddl);
        }

        if (!self::tableExists('heats')) {
            static::query("
                CREATE TABLE heats (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (!self::tableExists('lane_allocations')) {
            static::query("
                CREATE TABLE lane_allocations (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (!self::tableExists('results')) {
            static::query("
                CREATE TABLE results (
                    id                   INT AUTO_INCREMENT PRIMARY KEY,
                    event_id             INT NOT NULL,
                    round_id             INT NOT NULL,
                    heat_id              INT NOT NULL,
                    lane_allocation_id   INT NOT NULL,
                    team_registration_id INT NOT NULL,
                    race_time            VARCHAR(20) NULL,
                    time_centis          INT UNSIGNED NULL,
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        self::$applied['rounds'] = true;
    }

    /** Global key/value settings for the platform owner. */
    public static function ensureAppSettings(): void
    {
        if (!empty(self::$applied['app_settings'])) return;

        if (!self::tableExists('app_settings')) {
            static::query("
                CREATE TABLE app_settings (
                    setting_key   VARCHAR(80) PRIMARY KEY,
                    setting_value TEXT NULL,
                    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        self::$applied['app_settings'] = true;
    }

    /** Everything, in dependency order. Used by the installer route. */
    public static function ensureAll(): void
    {
        self::ensureUsers();
        self::ensureEvents();
        self::ensureEventAdmins();
        self::ensureEventUsers();
        self::ensureTeams();
        self::ensureRaces();
        self::ensureRounds();
        self::ensureAppSettings();
    }

    // ── INFORMATION_SCHEMA guards ────────────────────────────────────────────

    private static function tableExists(string $name): bool
    {
        return (bool)static::row(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$name]
        );
    }

    private static function columnExists(string $table, string $column): bool
    {
        return (bool)static::row(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );
    }

    private static function indexExists(string $table, string $index): bool
    {
        return (bool)static::row(
            "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
              LIMIT 1",
            [$table, $index]
        );
    }
}
