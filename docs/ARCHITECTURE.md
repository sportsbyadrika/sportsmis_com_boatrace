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

### The event admin can stand in for the race office

An Event Admin owns their event outright — its teams, programme, and the
accounts that run it — so there is nothing to escalate by letting them into
the race office. Without it, publishing one result or putting the LED wall up
means creating a throwaway Event User account, which is how those proliferate.

**Race Office** in the admin nav (or the panel on their dashboard) opens
`$_SESSION['event_user']` with `via_admin => true`, `id => 0` and every
privilege. Three things keep that honest:

- **The flag is never trusted.** `EventUserBase::bootAsAdmin()` re-reads the
  administrator's own session and account on *every* request, and checks the
  account is still active and still belongs to that event. The access dies the
  moment the administrator session does; it cannot be replayed.
- **It is visible.** A standing banner says whose access it is, the avatar
  menu reads "Event Admin — in the race office", and the only way out is
  labelled *Back to Event Admin* rather than Logout — because signing out is
  not what leaving does.
- **`?to=` cannot leave the site.** The landing page comes from a whitelist of
  `/event-user/…` paths, never from the query string. `tools/selftest.php`
  asserts every entry stays inside the race office, so an open redirect
  cannot be introduced by adding a row.

A stand-in has no event-user password, so the change-password modal is not
rendered for one and the endpoint refuses it.

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

## 7. Serving public results at scale

On a big race day the public page is the only part of this system under real
load — tens of thousands of people refreshing at once, on phones, on a crowded
network. Nothing dynamic survives that on shared hosting, so the public path
executes no PHP and touches no database.

### How it works

`Services\ResultSnapshot::publish()` renders the whole event to static files
under `app/public/live/<EVENT_CODE>/`:

| File | Role | Cache |
|---|---|---|
| `manifest.json` | ~150 bytes: current version + payload filename | `max-age=5, stale-while-revalidate=30` |
| `results-<v>.json` | the payload | `max-age=31536000, immutable` |
| `index.html` | the page itself, plain HTML + JS | `max-age=60` |

The client polls only the **manifest**. The payload's name changes with its
version, so it is immutable and fetched exactly once per change — a browser or
CDN never revalidates it. That is the difference between 30,000 clients pulling
200 KB every 10 seconds (600 MB/s, hopeless) and pulling 150 bytes on the same
cadence, mostly served from cache.

### Writes are atomic

Every file goes to a temporary name **in the same directory**, is flushed and
`fsync`ed, has its permissions set, and is then `rename()`d over the target.
`rename()` is atomic within a filesystem, so a reader gets the whole old file
or the whole new one — never a half-written response. Permissions are set
before the rename, or a reader could catch the file in the instant between
appearing and becoming readable.

**Ordering matters as much as atomicity:** the payload is written first and the
manifest last. Until the manifest points at the new payload, clients keep using
the old one, so the swap is never half-applied. Reversing that order would
publish a manifest naming a file that does not exist yet.

Old payloads are kept for a few versions so requests already in flight still
resolve, and stale temp files from an interrupted publish are cleaned up.

### What the page shows

A race card is a **summary**: the thumbnail, the state badge, and the top three
rows — placings if the deciding round is published, qualifiers if only an
earlier round is out, entered boats if nothing is. Tapping the card opens the
full list.

The detail view is a bottom sheet on a phone and a dialog on a desktop, built
on `<dialog>` with a fallback for browsers without `showModal()`. It is **not
a plain modal**: every race is addressable at `#race-<sl>`, so opening one
pushes a history entry. That buys three things a modal normally costs —
a result can be shared as a link, the back button closes the sheet instead of
leaving the page, and someone arriving on a shared link lands directly on that
race.

Each card carries a second link, bottom right: **Heats & rounds**. It opens the
same sheet on a tab listing every *published* round of that race in ladder
order — each heat, each lane, the boat's lane number, its time, DNS/DNF/DSQ
where it applies, and a Q against whoever went through. That is how a reader
gets from "Champakulam won" to "here is the heat it qualified from". Rounds
still in draft are left out of the payload entirely: an undrawn or unrowed
round is not public information. The tab is addressable too (`#race-1-rounds`),
and switching tabs *replaces* the history entry rather than pushing one, so
Back always closes the sheet instead of stepping between its tabs.

The rows themselves are a badge, a two-line name block and a time, laid out
with flex. The name block must be a **block-level box**: as inline spans the
boat and the club ignored `overflow`/`text-overflow`/`white-space` entirely,
printed on the same line, and ran across the finishing time — the layout bug
this cost a race day to notice. `tools/ui-test.js` measures the boxes in a real
browser at three profiles; restoring the inline spans fails it six times.

The search bar had the same shape of fault. `applyFilter()` set `card.hidden`,
which does nothing here: `.card` carries `display:flex`, and an author
declaration out-ranks the browser's own `[hidden] { display: none }` rule. The
filter counted correctly and even printed "No races match" while leaving every
card on screen. `.card[hidden] { display: none }` fixes it, and the browser
test now asserts on what is actually *visible*, never on the attribute.

### What is NOT solved here

A CDN. This design removes PHP and MySQL from the hot path and makes the
responses cacheable, which is the necessary half — but a single shared-hosting
origin still has to serve the first request from every cold client. **Put
Cloudflare (or any CDN) in front of the subdomain and let it cache `/live/`.**
The origin then sees a handful of requests per minute regardless of crowd size.
Without that, the file layout helps but the origin is still the ceiling.

## 8. Why vendor/ is committed

Dompdf is the only dependency. It is pure PHP with no build step, and the
cPanel deployment shell has no composer on its `PATH` — the guarded
`composer install` in `.cpanel.yml` silently did nothing, and every PDF route
served "PDF renderer unavailable" while the browser print views kept working.

So `vendor/` ships inside the repository and a deploy needs no composer at
all. That makes it a build artefact living in git, which is only safe if it is
regenerated reproducibly: use `./tools/vendor-refresh.sh`, which installs
`--no-dev --prefer-dist`, strips each package's own `.git`, `.github`, tests
and docs, re-runs the self-test, and reports the resulting size.

The stripping is not cosmetic. Composer falls back to cloning from source when
it cannot use dist archives, leaving a `.git` inside every package — ~44 MB of
a ~56 MB install, and git would treat each one as a submodule. Pruned, the
tree is ~12 MB across ~660 files, most of it the DejaVu fonts Dompdf needs to
set regional-language text.

`tools/selftest.php` asserts `vendor/autoload.php` is present, that git
actually tracks it, and that no package carries a nested `.git` — so this
cannot silently regress into a dead PDF button again.

## 9. Known gaps

- No email delivery, so credentials must be copied from the flash message.
- No pagination on the team or programme lists; filtering is client-side,
  which is right for a regatta's scale but not for thousands of rows.
- Times are stored to milliseconds but entered by hand — there is no timing
  hardware integration.
- `tools/selftest.php` covers the pure logic and the PDF pipeline; the
  database-backed paths have no automated test because the build environment
  has no MySQL.
- `tools/ui-test.js` is optional: it needs Node, `playwright-core` and a
  Chromium build, none of which the hosting account has. It is a development
  check, not part of a deploy.
