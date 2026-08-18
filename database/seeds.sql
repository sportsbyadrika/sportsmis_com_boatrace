-- ═══════════════════════════════════════════════════════════════════════════
-- SportsMIS® Regatta — optional seed data.
--
-- Run AFTER database/schema.sql:
--   mysql -u USER -p sportsmis_regatta < database/seeds.sql
--
-- Contents:
--   1. the bootstrap Super Admin (also created automatically on first run
--      by Models\Schema::ensureUsers(), so this is only for a manual install);
--   2. sensible platform defaults;
--   3. a small demo regatta, commented out — uncomment to get a populated
--      event to click through.
--
-- SECURITY: the bootstrap password below is public knowledge. Change it the
-- moment you sign in; the admin dashboard nags until you do.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── 1. Bootstrap Super Admin ───────────────────────────────────────────────
-- email: admin@sportsmis.com   password: ChangeMe@123
INSERT INTO users (name, email, password, role, status)
VALUES ('Super Admin', 'admin@sportsmis.com',
        '$2y$12$YuX8gvxxE3ew52cIwZX3DulQI/cePRUhakxSj1jc074fH/MYHHqV.',
        'super_admin', 'active')
ON DUPLICATE KEY UPDATE email = email;

-- ── 2. Platform defaults ───────────────────────────────────────────────────
INSERT INTO app_settings (setting_key, setting_value) VALUES
    ('platform_name',    'SportsMIS® Regatta'),
    ('support_email',    'support@sportsmis.com'),
    ('default_lanes',    '6'),
    ('default_chroma',   '#00b140'),
    ('programme_footer', 'Powered by SportsMIS® Regatta')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ── 3. Demo regatta (commented out) ────────────────────────────────────────
-- Uncomment the block below for a populated event to explore. The event
-- admin and event user both sign in with Event Code RGDEMO01 and the
-- password ChangeMe@123.
--
-- INSERT INTO events (code, name, name_regional, venue, district, organiser,
--                     start_date, end_date, status, default_lanes, chroma_color,
--                     display_pin, slide_seconds)
-- VALUES ('RGDEMO01', 'Demo Regatta 2026', 'ഡെമോ വള്ളംകളി', 'Punnamada Lake',
--         'Alappuzha', 'District Sports Council', '2026-08-08', '2026-08-09',
--         'active', 4, '#00b140', '2468', 9);
-- SET @ev := LAST_INSERT_ID();
--
-- INSERT INTO event_admins (event_id, name, email, password, status) VALUES
--   (@ev, 'Demo Event Admin', 'eventadmin@example.com',
--    '$2y$12$YuX8gvxxE3ew52cIwZX3DulQI/cePRUhakxSj1jc074fH/MYHHqV.', 'active');
--
-- INSERT INTO event_users (event_id, name, email, designation, password, status) VALUES
--   (@ev, 'Demo Race Office', 'raceoffice@example.com', 'Chief Judge',
--    '$2y$12$YuX8gvxxE3ew52cIwZX3DulQI/cePRUhakxSj1jc074fH/MYHHqV.', 'active');
-- SET @eu := LAST_INSERT_ID();
--
-- INSERT INTO event_user_privileges (event_user_id, privilege) VALUES
--   (@eu, 'rounds_heats'), (@eu, 'lane_allocation'),
--   (@eu, 'result_entry'), (@eu, 'reports'), (@eu, 'displays');
--
-- INSERT INTO teams (event_id, club_name, boat_name, captain_name, boat_class, short_code) VALUES
--   (@ev, 'Nadubhagom Boat Club', 'Nadubhagom Chundan', 'K. Menon',   'Chundan Vallam', 'NBC'),
--   (@ev, 'Karichal Boat Club',   'Karichal Chundan',   'S. Pillai',  'Chundan Vallam', 'KBC'),
--   (@ev, 'Veeyapuram Boat Club', 'Veeyapuram Chundan', 'R. Nair',    'Chundan Vallam', 'VBC'),
--   (@ev, 'Payippad Boat Club',   'Payippad Chundan',   'A. Kurian',  'Chundan Vallam', 'PBC');
--
-- INSERT INTO team_registrations (event_id, team_id, status, submitted_at, decided_at, decided_by)
--   SELECT @ev, id, 'approved', NOW(), NOW(), 'Seed' FROM teams WHERE event_id = @ev;
--
-- INSERT INTO event_races (event_id, sl_no, code, name, name_regional, boat_class,
--                          gender, distance_m, lane_count, race_date, race_time, status)
-- VALUES (@ev, 1, 'R1', 'Chundan Vallam', 'ചുണ്ടൻ വള്ളം', 'Chundan Vallam',
--         'men', 1400, 4, '2026-08-09', '15:00:00', 'scheduled');
-- SET @race := LAST_INSERT_ID();
--
-- INSERT INTO race_entries (event_id, event_race_id, team_registration_id)
--   SELECT @ev, @race, id FROM team_registrations WHERE event_id = @ev;
--
-- INSERT INTO rounds (event_id, event_race_id, name, round_type, sort_order,
--                     lane_count, qualify_per_heat, status) VALUES
--   (@ev, @race, 'Preliminary Heats', 'preliminary', 1, 4, 2, 'draft'),
--   (@ev, @race, 'Final',             'final',       2, 4, 0, 'draft');
