# Architecture & data model

Notes on how SportsMIS Regatta is put together, and on the places where the
boat-race domain diverged from the generic SportsMIS one. **The decisions
marked ⚠ are the ones worth a second opinion** — they were made to keep the
build moving and are cheap to change.

---

## 1. Tenancy

`events` is the tenant boundary. Every team, race, round, heat, lane and
result carries an `event_id`, and every per-event controller resolves its
event in `boot()` and scopes each query to it. Nothing is global except the
`users` table (platform accounts) and `app_settings`.

The **Event Code** (`events.code`, e.g. `RG1A2B3C`) is the tenant's public
handle. It is minted on first use by `ensureEventCode()`, never changes, and
is what event admins, event users and display operators all type.

## 2. The three session buckets

`Core\Auth` keeps three independent buckets in one PHP session:

| Bucket | Table | Opened for |
|---|---|---|
| `$_SESSION['user']` | `users` | platform accounts |
| `$_SESSION['event_admin']` | `event_admins` | event administrators |
| `$_SESSION['event_user']` | `event_users` | race office |

This mirrors SportsMIS's `event_staff` bucket, and it means a platform owner
debugging an event portal doesn't lose their admin session. Per-event
credentials never touch `users`; uniqueness is per `(event_id, email)`, so one
person can hold accounts on several regattas with one address.

### One sign-in form

There is a single `/login` taking email + password.
`AuthController::resolveCandidates()` verifies the password against all three
tables and returns every account it opens; `signIn()` then opens the matching
bucket. Three consequences worth knowing:

- **The page reveals nothing.** It previously had a tab per role, which
  advertised the entire role structure to anyone who loaded it.
- **Failures are indistinguishable.** "No such address", "wrong password" and
  "account disabled" all produce the same message.
- **Ambiguity is resolved after verification.** When a password opens several
  accounts, the candidate list — ids only, no password material — is held in
  `$_SESSION['login_choices']` for five minutes and the user picks. The POST
  carries an index into that list, never an account id, so it cannot name an
  account the session hasn't already proved the password for.

The Event Code is no longer typed at sign-in. It remains the tenant's public
handle — on the programme, on reports, and at `/display` — but it is not a
credential, and treating it as one gave a false impression of security.

`EventUserBase::boot()` re-reads the account's privileges from the database on
every request rather than trusting the session copy, so a grant or revoke by
the event admin takes effect immediately instead of on next sign-in.

## 3. Entity model

```
events
├── event_admins
├── event_users ── event_user_privileges
├── teams ─────── team_registrations ──┐
└── event_races ── race_entries ───────┘
        └── rounds
             └── heats
                  └── lane_allocations ── results
```

### teams vs team_registrations ⚠

The spec described `teams` as belonging to an event *and* `team_registrations`
as linking a team to an event, which is circular. Resolved as:

- **`teams`** — the event's club/boat master. Event-scoped, so the same club
  entering two regattas has one row per event and a boat can be renamed or
  re-crewed between years without rewriting history.
- **`team_registrations`** — that boat's entry into the event, carrying the
  `draft → submitted → approved / returned` workflow. One row per
  `(event_id, team_id)`.
- **`race_entries`** — the "and later to specific event races" half, split
  into its own table: which registered boats contest which programme item.

The alternative was one `team_registrations` table with a nullable
`event_race_id` doing both jobs, which MySQL can't uniquely constrain (NULLs
don't collide) and which reads worse. If you'd rather the two were merged,
it's a contained change.

### Bulk team upload

`Services\TeamImport` parses a CSV into validated rows; `Team::importRows()`
commits them in one transaction. Two decisions worth knowing:

- **Two steps, always.** The upload is parsed and shown back row by row before
  anything is written. Rows that fail validation are displayed but never
  imported, so a bad spreadsheet cannot half-land.
- **Registrations only move forward.** The upload form chooses the state
  imported teams open in (draft / submitted / approved), and
  `TeamRegistration::applyImportedStatus()` refuses to walk a registration
  backwards. Re-uploading a corrected sheet can never un-approve a boat that
  has already been vetted — and possibly already drawn into a lane. Note this
  is guarded twice: `draft` returns before the rank check, and the rank check
  itself stops `submitted` demoting an `approved` row.
- **Logos are never touched.** A spreadsheet cannot carry an image, so an
  update writes every column except `logo`.

Matching an uploaded row to a team already on file uses the short code when
one is given, and club + boat name otherwise, both case-insensitively.

### A boat's photo is per race, not per team

`teams.logo` is the club crest. `race_entries.image` is a photo of the boat
**as it races in that particular race**, because the same club may field a
different boat in each one.

That put a trap in `EventRace::setEntries()`, which used to clear the whole
entry list and re-insert it. Ticking one more box would have destroyed every
photo already uploaded for that race. It is now written as a diff — drop what
is no longer wanted, insert what is new, leave the rest untouched — and
`tools/selftest.php` pins that down: reverting to delete-and-reinsert fails
three checks.

### A race runs the rounds it actually rows

There is no fixed ladder. `Round::STANDARD_ROUNDS` lists the four a race may
be given — Preliminary Heats, Quarter-Finals, Semi-Finals, Final — each with
its default qualifier rule and a **ladder rank**. The Event Admin ticks the
ones this race runs, which is commonly heats plus a final, and sometimes a
final alone.

The rank is what makes the tick-list safe: `resequenceByLadder()` orders
rounds by rank rather than by insertion, so adding quarter-finals to a race
that already has a final still places them before it. Ordering by insertion
instead fails three checks in `tools/selftest.php`.

Removing a round takes its heats, lane draw and results with it, so the
button spells out what goes and a **locked or published round is refused
outright** — unlocking is the race office's decision, not the programme's.

### Scheduling cascades: race → round → heat

A regatta is scheduled at three levels, and each level below the race is
optional:

| Level | Column | Blank means |
|---|---|---|
| `event_races` | `race_date`, `race_time` | unscheduled |
| `rounds` | `scheduled_date`, `scheduled_time` | inherit the race |
| `heats` | `scheduled_date`, `scheduled_time` | inherit the round |

This is what lets preliminary heats, semi-finals and the final run at
different times or on different days without duplicating a schedule onto
every heat. `effectiveSchedule()` resolves it, and **date and time inherit
independently** — semis later the same day need only a time.

Two rules that are easy to get wrong and are therefore pinned by
`tools/selftest.php`:

- A heat inherits its **round**, not the race. Skipping the middle level
  silently strands every heat of a round that moved to another day.
- `'0000-00-00'` and `'00:00:00'` count as *not set*. Treating a zero date as
  a real override would let a placeholder beat a genuine date from above.

The Event Admin sets round slots from **Order of Events → Schedule** (and may
seed the standard ladder there, so a race can be timed before the race office
opens it); the race office can override per round or per heat under **Rounds &
Heats**. The printed programme shows a round summary under each race, and
prints nothing extra for a race that runs straight through.

### Rounds own the lane count ⚠

`lane_count` lives on `rounds`, not on the event or the race, because a final
frequently runs fewer lanes than the heats that fed it. `events.default_lanes`
and `event_races.lane_count` are only starting values for a new round.

Narrowing a round's lane count is **refused** while a boat still occupies a
lane above the new limit, rather than silently stranding it
(`LaneAllocation::maxLaneUsed()`).

### The two unique keys that hold the lane board together

```sql
UNIQUE KEY uq_lane       (heat_id, lane_no)                 -- one boat per lane
UNIQUE KEY uq_round_team (round_id, team_registration_id)   -- one lane per boat per round
```

Both are enforced in MySQL, not just in PHP, so a double-tap on the drag
handler or two operators working the same round cannot produce a duplicate.

`LaneAllocation::moveOrSwap()` performs a move-or-swap in a single
transaction, parking the source row on `lane_no = 0` — a value no real draw
uses — so `uq_lane` is never violated mid-swap.

### Advancement

A race's **first** round draws from its approved `race_entries`. Every
**later** round draws from the boats flagged `qualified` in the round before
(`Result::qualifiersForRound()`), shaped identically so the lane board treats
the two pools alike. That single rule is what makes the ladder work; there is
no separate "advance" step to forget to run.

`Result::autoQualify()` ticks the top N finishers of each heat, where N is the
round's `qualify_per_heat` (0 for a final). The ticks stay editable by hand.

### Positions and times ⚠

`results` stores both `race_time` (normalised to `MM:SS.mmm`) and `position`,
because a judge may have one, the other or both:

- a position typed by hand always wins;
- blank positions are derived from the recorded times, fastest first, skipping
  numbers already claimed by hand (`Result::derivePositions()`);
- a non-`ok` outcome (DNS / DNF / DSQ) clears both time and position, so a
  boat that did not finish can never appear on a rank list.

`time_centis` is the sortable projection of `race_time`;
`normaliseRaceTime()` accepts `4:12.35`, `1:23`, bare `83.45` and comma
decimals, and rejects anything else so the controller can complain rather than
store junk.

### Publishing is the visibility gate

`rounds.status` runs `draft → open → locked → published`. Reports, the rank
list, the club tally and both display screens read **published rounds only**.
Publishing a race's last round also moves the programme item to
`result_published`, keeping the Order of Events honest without a second step.

The event-wise rank list takes 1st–4th from each race's **last published**
round, so a race whose final is still provisional shows "no published result
yet" rather than its semi-final places.

## 4. Request lifecycle

1. `app/public/index.php` loads `app/.env`, installs an exception handler,
   registers the PSR-style autoloader, starts the session and builds the route
   table.
2. `Core\Router` matches on a regex and hands `{param}` values to the
   controller method positionally.
3. The controller's private `boot()` self-heals the schema, gates on the right
   session bucket and loads the tenant event. There is no middleware.
4. `renderWith($layout, $view, $data)` requires the layout, which requires the
   view. The per-area base controllers wrap this as `$this->view()`.

## 5. Security posture

- **CSRF** on every mutating POST. `verifyCsrf()` also detects the
  `post_max_size` overflow shape (empty `$_POST` with a non-zero
  `CONTENT_LENGTH`) and returns "upload too large" rather than a bare 403.
- **Obfuscated URL ids.** `Core\Hash` HMAC-signs each id under a context
  string, so an event token cannot be replayed as a team id — `selftest.php`
  asserts exactly that.
- **Eligibility is re-checked server-side.** Every lane endpoint re-verifies
  the round is unfrozen, the heat belongs to the round, the lane is inside the
  round's lane count and the boat is in this round's pool, so a hand-crafted
  POST cannot bypass the UI.
- **Uploads** go through `Core\FileUpload`, which checks size, extension and
  MIME, and stores under a random filename.
- **Display screens** need no app session; a per-event PIN gates them when set
  and the grant is remembered per event in the session.

## 6. Extension points

- **Public pages** — `Controllers\PublicController`, `app/views/public/`, and
  the `PUBLIC` section of the route table. Deliberately a stub.
- **Mail** — `app/config/app.php` carries SMTP settings; there is no mailer
  yet, so generated passwords are shown once in a flash message. Dropping in
  a `Core\Mailer` and calling it where those flashes are raised is the whole
  job.
- **Round types** — `Round::TYPES` and `Round::DEFAULT_LADDER` are constants;
  add a type or change the seeded ladder in one place.
- **Privileges** — `EventUser::PRIVILEGES` plus `PRIVILEGE_META` drive the
  nav, the dashboard cards and the account form. Adding one means adding a
  `requirePrivilege()` call in the new controller and nothing else.

## 7. Known gaps

- No email delivery, so credentials must be copied from the flash message.
- No pagination on the team or programme lists; filtering is client-side,
  which is right for a regatta's scale but not for thousands of rows.
- Times are stored to milliseconds but entered by hand — there is no timing
  hardware integration.
- `tools/selftest.php` covers the pure logic and the PDF pipeline; the
  database-backed paths have no automated test because the build environment
  has no MySQL.
