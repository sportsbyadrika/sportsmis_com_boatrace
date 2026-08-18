# SportsMIS® Regatta

Boat race event management, built as a sibling product to
[SportsMIS](https://sportsmis.com): the same stack and the same visual
language, deployed on its own URL (`regatta.sportsmis.com`) with its own
database.

---

## What it does

| Role | Signs in at | Manages |
|---|---|---|
| **Super Admin** (platform owner) | `/login` — email + password | Events, one or more Event Admin accounts per event, platform defaults |
| **Event Admin** (per event) | `/event-admin/login` — **Event Code** + email + password | Event details, teams, registrations, the Order of Events, Event User accounts |
| **Event User** (per event, privilege-gated) | `/event-user/login` — **Event Code** + email + password | Rounds & heats, lane allocation, result entry, reports, display screens |

Each role has its own session bucket, so a platform owner can hold an admin
session and an event portal session at once without one clobbering the other.

**Event Users** hold a subset of five privileges — `rounds_heats`,
`lane_allocation`, `result_entry`, `reports`, `displays`. The nav hides what
an account cannot do, and every controller action re-checks, so a hidden page
cannot be reached by URL either.

### Race-day flow

```
Order of Events   →   Rounds        →   Heats        →   Lane draw   →   Results
(programme item)      (Prelim /         (n per round,     (drag boats     (times, positions,
                       Semi / Final)     lane count        onto lanes)     qualifiers)
                                         per round)
                                                                              │
                        ┌─────────────────────────────────────────────────────┘
                        ▼
                   Publish round  →  Reports (rank list 1st–4th, heat sheets)
                                  →  Display screens (LED wall, stream overlay)
```

Nothing provisional escapes: reports and both display screens read
**published rounds only**, and only an **approved** team registration can be
drawn into a lane.

---

## Stack

Deliberately the same as SportsMIS, with no framework:

- **PHP 8.1+**, a small custom MVC
- **MySQL via PDO**, wrapped in `Core\Model` (`query/row/rows/value/insert/update/delete/transaction`)
- **`Core\Router`** — regex routes, all registered in `app/public/index.php`
- **`Core\Controller`** — `render` / `renderWith` / `redirect` / `json` / `abort` / `verifyCsrf` / `validate`
- **`Core\Auth`** — session auth with three independent buckets
- **`Models\Schema`** — idempotent, self-healing `ensureX()` migrations
- **Views** — plain PHP under `app/views/`, five layouts (`app`, `auth`, `event`, `print`, `public`)
- **Frontend** — Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 (CDN), Inter, and the SportsMIS `sms-*` component classes in `/assets/css/app.css`
- **PDFs** — Dompdf behind `Core\Pdf::stream()`, plus a `print` layout for browser "Save as PDF"

Only one Composer dependency: `dompdf/dompdf`.

---

## Install

```bash
git clone <this repo> && cd sportsmis_com_boatrace
composer install                        # vendors Dompdf

cp app/.env.example app/.env            # then fill in APP_SECRET and the DB_* values
mysql -u USER -p -e "CREATE DATABASE sportsmis_regatta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u USER -p sportsmis_regatta < database/schema.sql
mysql -u USER -p sportsmis_regatta < database/seeds.sql     # optional
```

`app/.env` is gitignored and read on every request by `app/public/index.php`.
Change `APP_SECRET` — it is the HMAC key behind the obfuscated URL ids, and the
shipped default is public knowledge.

Point the web server's document root at **`app/public/`**. Apache picks up
the bundled `.htaccess`; on nginx, route everything to `index.php`:

```nginx
root /var/www/regatta/app/public;
location / { try_files $uri $uri/ /index.php?$query_string; }
```

The `app/public/assets/uploads/` tree and `storage/dompdf/` must be writable.

### Deploying on cPanel

`.cpanel.yml` drives cPanel's Git™ Version Control deployment:

| | |
|---|---|
| Repository path | `/home/olympicd/repositories/sportsmis_com_boatrace` |
| Deployment path | `/home/olympicd/olympicday.in` |

Each deploy copies the working tree across, strips `.git`, `.cpanel.yml` and
`.gitignore` from the target, runs `composer install --no-dev` when composer is
on `PATH`, and recreates the writable upload and Dompdf directories.

Two things to set up once, by hand:

1. **Document root.** Point the domain at
   `/home/olympicd/olympicday.in/app/public`, *not* at the deployment path —
   otherwise `app/.env`, `app/config/` and `database/` are served to the web.
2. **`app/.env`.** It is gitignored, so it never reaches the repository and no
   deploy touches it. Create it once on the server from `app/.env.example`.

No migration step is needed: the `Schema::ensureX()` calls upgrade the database
on the next request. For a brand-new database, import `database/schema.sql`
first.

**First sign-in:** `admin@sportsmis.com` / `ChangeMe@123`. The account is
created automatically on first run even without `seeds.sql`, and the
dashboard nags until the password is changed.

### Schema upgrades

There is no migration command. Every controller calls the
`Models\Schema::ensureX()` it depends on from its `boot()`, and each of those
checks `INFORMATION_SCHEMA` before running any `CREATE` or `ALTER`. Deploying
new code is enough — the database upgrades itself on the next request.
`database/schema.sql` is kept in step for fresh installs.

---

## Checks

Neither needs a database:

```bash
php tools/verify.php     # lint, route→method arity, view/layout targets, inline JS
php tools/selftest.php   # time parsing, ordinals, hidden ids, seed hash, PDF rendering
```

`tools/verify.php` catches the mistakes this architecture actually invites — a
route pointing at a method that doesn't exist, a `renderWith()` naming a view
that was never written, a controller path parameter count that doesn't match
its signature, and syntax errors inside the views' inline `<script>` blocks.

---

## Layout

```
app/
  config/       app.php, database.php
  core/         Router, Model, Controller, Auth, Hash, FileUpload, Pdf, helpers.php
  models/       Schema, User, Event, EventAdmin, EventUser, Team, TeamRegistration,
                EventRace, Round, Heat, LaneAllocation, Result, AppSetting
  controllers/  Auth · Admin{,Event,Account} · EventAdmin{,Team,User,Race}
                EventUser{,Round,Lane,Result,Report,Display} · Display · Public
  views/
    layouts/    app · auth · event · print · public
    partials/   brand, role-tabs, print-head, pdf-head
    admin/ event-admin/ event-user/ displays/ auth/ errors/ public/
  public/       index.php (bootstrap + every route), .htaccess, assets/
database/       schema.sql, seeds.sql
docs/           ARCHITECTURE.md
tools/          verify.php, selftest.php
```

---

## Conventions

- **CSRF on every mutating POST** — `csrf()` in the form, `verifyCsrf()` first
  in the action. AJAX callers get a JSON 403 rather than an HTML error page.
- **Escape all output** with `e()`.
- **Obfuscated URL ids** — integer ids never appear in a URL. `hid_event()`,
  `hid_team()`, `hid_round()` … each sign the id under its own context, so a
  token minted for an event cannot be replayed as a team id.
- **Models own all SQL.** Controllers never build a query.
- **Gating lives in `boot()`.** There is no middleware layer.
- **AJAX pattern** — forms POST `FormData` via `fetch()` to endpoints
  returning JSON; `window.rgPost()` attaches the CSRF token and
  `window.rgToast()` reports the outcome.
- **Responsive** — tables scroll inside `.table-responsive`; the display
  screens size everything in `vh`/`vw`.

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the data model and the
domain decisions behind it.

---

## Public pages

`Controllers\PublicController` and `app/views/public/` are a deliberate stub —
the spectator-facing experience is still to be specified. The pieces it will
need are already in place: the chrome-free `public` layout,
`Result::rankListForEvent()` / `::heatSheet()` (published rounds only) and
`Event::findByCode()` for the shareable Event Code. Add routes under the
`PUBLIC` section of `app/public/index.php`.

---

© SportsByA Tech (OPC) Private Limited · Powered by **SportsMIS®**
