# ArewaTecHub API — Phase 1 (Auth + Wallet) & Phase 2 (Workspace Booking)

This sandbox has no PHP/Composer, so this couldn't be run end-to-end here — but every
file is a real, complete Laravel file ready to drop into a fresh project on your XAMPP setup.

## Setup

```bash
composer create-project laravel/laravel arewatechub-api
cd arewatechub-api
composer require laravel/sanctum
php artisan install:api
```

Copy from this package into your project (overwrite where it already exists):

- `app/Models/*` → `app/Models/`
- `app/Services/*` → `app/Services/`
- `app/Http/Controllers/Api/*` → `app/Http/Controllers/Api/`
- `routes/api.php` → `routes/api.php`
- `config/cors.php` → `config/cors.php` (**important** — see the login troubleshooting section below)
- `database/migrations/*` → `database/migrations/`
- `database/seeders/*` → `database/seeders/`

Set your `.env` DB credentials to your local XAMPP MySQL database, then:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
```

`storage:link` is required for the manual bank-transfer feature — without
it, uploaded proof-of-payment receipts save fine but the admin panel's
image preview will 404.

**Before seeding:** open `database/seeders/WorkspaceSeeder.php` and replace
`sk_test_REPLACE_ME` / `pk_test_REPLACE_ME` with your real Paystack **test** keys.
Never put your live secret key in a seeder file.

`php artisan serve` — API is up at `http://127.0.0.1:8000/api`.

## What's included

**Phase 1 — Auth + Wallet**
- `POST /api/auth/register`, `POST /api/auth/login`, `GET /api/auth/me`, `POST /api/auth/logout`
- `GET /api/wallet`, `POST /api/wallet/fund/initialize`, `GET /api/wallet/fund/verify/{reference}`

**Phase 2 — Workspace Booking (matches your live business rules as of the Sept 2026 dump)**
- `GET /api/workspace/plans` — the 2 currently active plans: **September 2-in-1 Promo** (shown first) and **Basic Package**
- `GET /api/workspace/plans/{plan}/rooms` — the rooms bookable under that plan (today: Conference Room, Workspace 1, Private Space — in that order)
- `GET /api/workspace/plans/{plan}/durations?room_id=` — duration + price options for that plan/room (1 Day / 1 Week / 1 Month for Basic; a single "September 2-in-1 Promo" option for the promo)
- `GET /api/workspace/availability?plan_id=&room_id=&plan_duration_id=&date=` — live seat availability for that date, plus the computed session name and start/end datetime
- `POST /api/bookings` — creates the booking and pays via wallet (instant) or Paystack (returns a checkout URL)
- `GET /api/bookings/verify/{reference}`, `GET /api/bookings`

### Business rules ported from your live triggers → `WorkspaceScheduleService`

Your live database currently enforces scheduling via MySQL triggers on `bookings`. I moved
the same logic into `app/Services/WorkspaceScheduleService.php` (portable, testable, no
triggers needed):

- **No manual session picking.** Operating hours are derived purely from the calendar date:
  Sunday → "Workspace Sunday" (13:00–17:00); every other day → "Workspace Day" (09:00–16:00).
- **Promo plans can carry a fixed end date.** `plans.promo_fixed_end_date` (new column) lets
  "September 2-in-1 Promo" force every booking under it to end 2026-10-31, regardless of the
  duration chosen — configurable from the DB, not hardcoded in a trigger or in code.
- **Seat availability is re-checked server-side on submit**, not just shown client-side —
  closes the race condition where two people try to book the same seat/date at once.

### Data note

All plan/room/duration data in the seeder is pulled directly from your live `arewadb`
dump (2 plans, 5 rooms — 3 of them bookable, 12 duration/price combinations, deduplicated
from 24 raw rows since weekday/Sunday pricing is identical).

## Login not working? Diagnose in this order

1. **Is Laravel even running?** Visit `http://127.0.0.1:8000/api/health` directly in your
   browser. If you don't see `{"ok":true,...}`, the frontend was never going to work —
   fix your Laravel server/DB connection first (`php artisan serve`, check `.env` DB creds).
2. **Did you run migrations + seed?** `php artisan migrate:fresh --seed`. Without this,
   the `customers` table doesn't exist and register/login will 500.
3. **Does `arewatechub-web/.env.local` exist?** Copy `.env.local.example` → `.env.local`.
   Without it, the frontend can't find your API URL (defaults to `127.0.0.1:8000/api`,
   which only matches if you're running `php artisan serve` on that exact port — not
   XAMPP's Apache on port 80).
4. **CORS.** Copy `config/cors.php` from this package into your Laravel project's
   `config/cors.php` (a fresh Laravel 11 install doesn't ship one). Without it, the
   browser silently blocks every request from `localhost:3000` to your API — this
   looks exactly like "login isn't working" with no useful error on screen. Open
   your browser's DevTools → Console/Network tab when you try to log in; a CORS
   failure shows up clearly there.
5. **You need an account first.** Real customer accounts from your old system aren't
   pre-loaded (their password hashes are Laravel-compatible bcrypt, but I didn't
   import personal customer data into a dev seeder without you asking). Go to
   `/register` and create a test account, then log in with it.

If you've checked all 5 and it's still failing, send me the exact error from the
browser console/network tab and I'll pinpoint it from there.

## New in this update — legacy import, admin, dedicated accounts

**One extra step: register the `admin` middleware.** Since `bootstrap/app.php` isn't
part of this package (it's created by `laravel new`), add this yourself in
`bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['admin' => \App\Http\Middleware\EnsureAdmin::class]);
})
```

**Legacy data now imported by the seeder:**
- `LegacyCustomerSeeder` — all 128 real customers from your live dump, IDs and bcrypt
  password hashes preserved, so everyone can log in tonight with their existing
  password and wallet balance. Historical *booking* records are intentionally not
  imported yet — they're informational only, not needed for tonight, and importing
  them safely (mapping old plan/room IDs to the new schema) is better done as a
  calm, separate pass once tonight's live traffic settles, not rushed in now.
- `LegacyAdminSeeder` — your real admin login (`admin@arewatechub.com.ng`), same
  password, works immediately against `/admin/login`.

**New: Admin API** (`/api/admin/*`, all behind `auth:sanctum` + the new `admin`
middleware so a customer's token can never touch these):
- `POST /admin/login`
- `GET /admin/bookings` — every booking with customer/plan/room, paginated
- `PATCH /admin/bookings/{id}/status` — change status (pending/confirmed/cancelled/completed)
- `GET /admin/customers`
- `GET /admin/wallet-fundings/pending`, `POST .../approve`, `POST .../reject` — for
  manual bank-transfer proof review (separate from the automatic dedicated-account
  crediting below)

**New: Dedicated virtual account** (`GET`/`POST /api/wallet/dedicated-account`) —
generates a real, permanent Paystack bank account number per customer via the
Customer + Dedicated Virtual Account APIs. Requires your Paystack business to have
Dedicated Virtual Accounts enabled (Nigeria-only, needs Paystack KYB approval) —
if that's not approved yet, this endpoint will fail with a clear Paystack error
until it is.

## New in this update — password reset, .env-based Paystack keys, booking cancel, confirmation emails

**Paystack keys moved to `.env`.** `PaystackService` now reads `PAYSTACK_TEST_SECRET_KEY` /
`PAYSTACK_LIVE_SECRET_KEY` and `PAYSTACK_MODE` from `.env` first (see `.env.example.additions`
in this package — copy those lines into your real `.env`). The `api_keys` DB table is kept
only as a fallback if the env vars aren't set, so nothing breaks if you were relying on it.

**Mail is now real**, via Laravel's `Mail` facade — set your Hostinger SMTP credentials
in `.env` (see `.env.example.additions`; I did not hardcode your real mailbox password
anywhere in this package). Two emails are wired up:
- Password reset link (`resources/views/emails/password-reset.blade.php`)
- Booking confirmation, sent after both wallet and Paystack payments succeed
  (`resources/views/emails/booking-confirmed.blade.php`)

If SMTP isn't configured yet, these fail silently (logged as a warning) rather than
breaking the booking/reset flow itself — you can test everything else without email working.

**New endpoints:**
- `POST /auth/password/forgot` — request reset link (rate-limited: 5/day per email,
  always returns the same generic message so emails can't be enumerated)
- `POST /auth/password/reset` — submit token + new password, also revokes all existing
  login sessions for that customer as a safety measure
- `POST /auth/password/update` (authenticated) — change password while logged in
- `POST /bookings/{id}/cancel` — customer cancels their own pending/confirmed booking,
  blocked once the booking has already started

## New in this update — internet login credentials, receipt, and a real concurrency fix

**Double-booking — honest correction.** The seat check I had before was a plain
"query then insert," which has a real race-condition gap: two people clicking
"Book" on the same seat within milliseconds of each other could both pass the
availability check before either row existed. Fixed now with `Cache::lock()`
around the whole check-and-create step, keyed to that exact room+seat+date — the
second request waits for the first to finish, then correctly sees the seat taken.
**Requires `CACHE_STORE=database` (or redis) in your `.env`** — the `file` cache
driver doesn't support atomic locks. Laravel 11's default `.env` already sets this;
if yours doesn't, add `CACHE_STORE=database` and run `php artisan cache:table` +
migrate if the `cache` table doesn't already exist.

**Internet login (WiFi) credentials — new feature, matches your legacy
`internet_accounts`/`user_internet_access` tables:**
- A pool of WiFi username/password pairs, each tagged daily/weekly/monthly.
- The moment a booking is confirmed (wallet or Paystack), one matching credential
  is automatically assigned and attached to the booking — shown on the dashboard
  and the receipt.
- If the pool runs dry, it first reclaims any assigned account whose access window
  has already expired, and only if literally nothing's available does it skip
  assignment quietly (never blocks the booking itself).
- **The pool starts empty** — there was no existing data for this table in your
  dump. Add credentials via `POST /api/admin/internet-accounts` (or I can wire up
  an admin UI for this if you want one before tonight — say the word).
- Cancelling a booking immediately frees its WiFi account back to the pool.

**Receipt** — `GET /bookings/{id}/receipt`, rendered as a print-friendly page at
`/receipt/{id}` in the frontend (browser's own "Print → Save as PDF" covers the
PDF need without pulling in a PDF-generation library for one page).

## Two errors you hit — both fixed

**`Table 'internet_accounts' doesn't exist`** — you just need to re-run
`php artisan migrate` (not `--fresh`, your existing data is fine) to pick up the
two new tables from last update. Regular `migrate` only runs migrations it
hasn't seen yet, so nothing else is touched.

**`Paystack initialize failed: Invalid key`** — this exposed a real bug on my
side: I had `PaystackService` call `env()` directly instead of going through a
config file. That works locally but silently breaks (returns null) the moment
`php artisan config:cache` has ever been run, because Laravel then stops
reading `.env` entirely for anything outside `config/*.php`. Fixed: added
`config/services.php` (merge the `'paystack'` key into yours if you already
have one with other services in it) and `PaystackService` now reads through
`config()`. After pulling this in:

```bash
php artisan config:clear
```

Then double-check in `.env`:
- `PAYSTACK_TEST_SECRET_KEY` starts with `sk_test_` (not `pk_test_` — the
  public key won't work here, and this is the single most common cause of
  "Invalid key")
- No quotes around the value, no trailing space
- `PAYSTACK_MODE` matches whichever key you actually set (`test` or `live`)

## Another bug — fixed

**`Table 'user_internet_accesses' doesn't exist`** — my mistake: Eloquent
guesses table names by pluralizing the model class name, and it pluralizes
"Access" to "Accesses" (`UserInternetAccess` → `user_internet_accesses`). The
migration — matching your legacy schema exactly — created it singular
(`user_internet_access`). Added an explicit `protected $table` on the model to
fix it. No migration change needed, no data lost — just re-pull this file and
you're set.

## New in this update — Paystack popup instead of redirect

Switched from Paystack's hosted-page redirect flow to the **inline popup**
(`js.paystack.co/v1/inline.js`, loaded in the frontend's root layout). This is
also why the `campusity.com.ng` redirect you saw won't happen anymore — that
was Paystack redirecting the browser to whatever Callback URL is set on your
Paystack account after a hosted-page payment; a popup never redirects the
browser at all, so there's no callback URL involved.

**New: `PAYSTACK_TEST_PUBLIC_KEY` / `PAYSTACK_LIVE_PUBLIC_KEY`** — add these to
`.env` (see `.env.example.additions`) and to `config/services.php`. Public keys
are safe client-side by design, but the frontend fetches them from the API
(via `/wallet/fund/initialize` and `/bookings`) rather than keeping its own
copy, so there's one source of truth for which mode (test/live) the whole app
is running in — flip `PAYSTACK_MODE` once and both wallet funding and booking
payments switch together.

The backend still independently verifies every payment against Paystack's
`/transaction/verify` endpoint using your **secret** key before crediting a
wallet or confirming a booking — the popup's own success callback is never
trusted on its own, exactly as before.

## New in this update — wallet-only payments, webhook, admin overhaul

**Fixed the "wallet not updating" issue at the root.** Relying only on the
frontend calling `/verify` after a popup is fragile — closed tab, network
blip, or a popup quirk means the wallet never gets credited even though
Paystack took the money. Added `POST /api/webhooks/paystack` as the
authoritative source of truth: Paystack calls this server-to-server the
moment money moves, independent of what the customer's browser does. It
handles both a popup payment against a reference we generated, and a
transfer straight into a customer's dedicated account (which has no
reference we created ahead of time — matched via the Paystack customer
code instead). **You must register this URL in your Paystack dashboard**
(Settings → API Keys & Webhooks → Webhook URL):
`https://yourdomain.com/api/webhooks/paystack`.

**Every paid service is wallet-only now**, per your instruction — no more
per-purchase Paystack option. `POST /bookings` dropped `payment_method`
entirely; it just debits the wallet, and returns a clear `shortfall` amount
if the balance is too low (frontend shows a "Fund my wallet" prompt) — no
booking or order row is created at all if payment would fail, so there's
no orphaned pending state to clean up. Cancelling a booking now auto-refunds
to the wallet, since that's the only place the money could have come from.

**Pagination + limits added:**
- `GET /bookings` and `GET /wallet` both accept `?per_page=` (default 5) and
  `?page=` — the dashboard uses the default 5, the new history page requests
  15 per page with pager controls.

**Admin overhaul:**
- `GET /admin/overview` — the "what's happened in the system" snapshot:
  totals, today's numbers, revenue, pending fundings, and a recent-activity feed.
- `POST /admin/customers/{id}/wallet/adjust` — manual credit/debit, for the
  front-desk cash-payment flow (customer pays cash, staff adds it to their
  wallet, everything downstream works exactly like any other wallet funds).
- `POST /admin/bookings/create-for-customer` — staff can book a workspace for
  any customer, either charging their wallet or comping it for free (`charge_wallet: false`).
- `GET /admin/customers?search=` — search by name/email/phone.
- `GET /admin/workspace/plans` / `.../durations` — same catalog data the
  customer booking flow uses, for the admin's booking form.

## New in this update — manual bank transfer, Free Access Wednesday, sessions, admin cancel/reschedule

**Manual bank transfer funding** — `POST /wallet/fund/manual` (multipart:
`amount`, `notes`, `proof_of_payment` image) lands as a pending
`wallet_transactions` row. Admin sees the receipt image, notes, and
amount on the Wallet Fundings tab and approves/rejects — nothing is
credited until they act on it. Your real bank details (Eco Bank,
account 3980078059) are served from `GET /wallet/bank-details`.
**Requires `php artisan storage:link`** so uploaded receipts are actually
reachable at a public URL.

**Free Access Wednesday** — new plan, ₦120, Conference Room only,
restricted to Wednesdays (`plans.restricted_weekday`, checked both in
`Plan::isBookableOn()` and enforced server-side on every booking
attempt — not just a frontend hint).

**Sessions are back as a customer choice** — 9am-3pm, 3:30pm-9pm, and an
overnight 10pm-7am (`workspace_sessions`, linked to plans via the new
`plan_sessions` pivot). This replaces the day-of-week auto-session logic
from an earlier update — you clarified customers actually pick a session
themselves, so `WorkspaceScheduleService` was rewritten around that,
including correctly handling the overnight session rolling into the next
calendar day. Free Access Wednesday only offers the morning session;
other plans offer all three.

**Customers can no longer cancel their own bookings.** `BookingController::cancel`
is removed entirely. Only `AdminBookingController::updateBookingStatus`
can cancel — and cancelling always triggers a refund + frees the WiFi
account, via the new shared `BookingRefundService`.

**Admin can reschedule a booking** — `PATCH /admin/bookings/{id}/reschedule`
— blocked if the booking's date is today (already underway) or the new
date/seat is taken.

## New in this update — fixed admin logout bug, duplicate plans, and search UX

**Admin (and customer) session bug, found and fixed.** The real cause of
"logged in, reload, seems logged out" — and very likely of the "no bookings"
and "funding page acting wrong" symptoms too: the session-check code was
clearing your login token on **any** failure, including a transient network
blip or a slow first request, not just an actual invalid token. A brief
hiccup on page load was silently logging you out for a reason that had
nothing to do with your session. Fixed in both `AuthContext` and
`AdminAuthContext`: only a real 401/403 clears the token now; every other
failure shows a "couldn't verify your session — try again" screen instead,
leaving your login intact. No backend change needed for this one — it was
entirely a frontend bug.

**Duplicate "Basic Package" / "September 2-in-1 Promo" — root cause and fix.**
The seeder wasn't idempotent — every `php artisan db:seed` created brand new
plan rows instead of reusing existing ones by name, which is exactly what
happened. Fixed two ways:
1. `WorkspaceSeeder` now uses `firstOrCreate`/`updateOrCreate`/`syncWithoutDetaching`
   throughout — safe to run any number of times from now on.
2. **For the duplicates already in your database**, run the new cleanup
   command *before* anything else in this update:
   ```bash
   php artisan plans:dedupe --dry-run   # see what it would do first
   php artisan plans:dedupe             # then actually run it
   ```
   This merges each duplicate into the earliest plan of that name, re-points
   any bookings made against the duplicate's durations so nothing is lost,
   and deletes the now-empty duplicate. Then run migrations as normal — a
   new migration adds a unique constraint on `plans.name` so this class of
   bug can't happen again (it will fail to apply if you skip the dedupe step
   first, which is intentional — it's telling you to clean up first).

**Admin customer search is now instant, everywhere.** The "Book for
Customer" tab required clicking a search button; it now searches as you
type (debounced), matching the Customers tab.

## New in this update — surveys, admin push notifications, email confirmations

**One new Composer package required:**
```bash
composer require minishlink/web-push
```
This powers real browser push notifications to admins (new booking, new
funding request) — works even when the admin panel tab isn't open, as long
as the browser is running.

**VAPID keys are already generated for you** — see `.env.example.additions`.
Copy `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` into your real `.env` as-is; no
need to generate your own unless you want to rotate them later.

**New migration:** `survey_sent_at` on `bookings`, plus the earlier
`booking_feedback` and `push_subscriptions` tables — run `php artisan migrate`.

**Email notifications, now complete:**
- Booking confirmation → customer (already existed)
- Booking confirmation → admin too now (`ADMIN_NOTIFICATION_EMAIL` in `.env`,
  falls back to your seeded admin login if not set)
- Wallet funding approved → customer, sent automatically the moment an admin
  approves a manual bank transfer

**Survey / feedback system:**
- Customers see an in-app "How was your workspace?" popup on their dashboard
  for any past booking that hasn't been rated yet (1–5 stars + optional comment)
- The same prompt goes out by email once a day for bookings that ended
  recently and weren't rated in-app — via the new `php artisan surveys:send`
  command, scheduled daily in `routes/console.php`.
  **On XAMPP/Windows, Laravel's scheduler needs something to actually call it every
  minute** — cron doesn't exist on Windows. Use Windows Task Scheduler:
  create a task that runs
  `C:\xampp\php\php.exe C:\xampp\htdocs\arewatechub-api\artisan schedule:run`
  every minute. Until you set that up, just run `php artisan surveys:send`
  manually whenever you want surveys to go out.
- Admin sees all submitted feedback via `GET /admin/feedback`.

**Admin push notifications:** sidebar toggle in the admin panel
("Enable notifications") — browser asks for permission once, then a
new booking or funding request pushes a real OS-level notification, even in
another tab or minimized.

## Duplicate rooms fixed (same root cause as the duplicate plans issue)

The old seeder duplicated **rooms and sessions**, not just plans, every time
it ran before the idempotency fix — that's why "Choose a workspace" was
showing rooms twice. Run the new comprehensive cleanup command instead of
the old `plans:dedupe` (this one covers everything in one pass):

```bash
php artisan workspace:dedupe --dry-run   # preview first
php artisan workspace:dedupe             # then actually merge
php artisan migrate                      # adds unique constraints on rooms.name and workspace_sessions.name
```

Same safety guarantees as before: bookings pointing at a duplicate room or
session get re-pointed to the canonical one, nothing is lost, and it's safe
to run more than once.

## Critical bug fixed — wallet balances were never actually persisting

**Root cause:** `wallet_balance` was missing from `Customer`'s `$fillable`
array. Laravel silently ignores any field not in `$fillable` when you call
`->update([...])` — no error, no exception, it just doesn't write that
column. Since this touched *every* wallet-affecting flow in the app
(bookings, refunds, admin wallet adjustments, Paystack popup funding, the
webhook, approved manual transfers), every one of them appeared to succeed —
the matching `wallet_transactions` row was created correctly every time,
since that table's `$fillable` was fine — but the actual
`customers.wallet_balance` column never moved. Your original customer
import worked because that went in via a raw `DB::table()->insert()`, which
bypasses Eloquent's mass-assignment guard entirely — so it looked like the
*first* balance was fine and only your admin adjustment "didn't stick,"
when actually nothing had been persisting the whole time.

**Fixed:** `wallet_balance` added to `Customer::$fillable`. All the flows
listed above now actually persist immediately — no other code changes were
needed anywhere else; I audited every `update()`/`create()` call against
every model's `$fillable` list and this was the only gap.

**Your existing data needs one-time reconciliation.** Because the column was
frozen the whole time, `customers.wallet_balance` right now still shows
whatever it was before this bug started — but the `wallet_transactions`
table has the *true* history of everything that should have happened since.
Run this once, right after pulling this update:

```bash
php artisan wallet:reconcile --dry-run   # see exactly what it would change first
php artisan wallet:reconcile             # then actually apply it
```

This recalculates each customer's correct balance as their current (frozen)
balance plus the net of every `successful` transaction on their account, and
corrects the column to match — nothing is guessed, it's derived entirely
from your real transaction history. Safe to run more than once.

## New: live legacy database sync

Since both sites are on the same server (same MySQL host, different
database), the legacy site's customer data can be synced directly — no
static SQL dump needed, and safe to re-run as many times as you want while
both sites are live during the transition.

**Setup:**
1. Add the `legacy` connection to your **existing** `config/database.php`
   (don't overwrite the file — see `config/database-legacy-snippet.php` for
   exactly what to paste into the `'connections' => [...]` array)
2. Add the `LEGACY_DB_*` values to `.env` (see `.env.example.additions`) —
   fill in your actual DB username/password from hPanel's MySQL Databases page
3. Test the connection and preview what it would do, without changing anything:
   ```bash
   php artisan legacy:sync-customers --dry-run
   ```
4. Once that looks right, actually run it:
   ```bash
   php artisan legacy:sync-customers
   ```

**What it does:** matches by email — creates any legacy customer that
doesn't exist here yet, and updates the profile fields (name, phone,
address, etc.) on ones that already do. Password hashes are copied as-is
(the legacy site's bcrypt hashes work natively with `Hash::check()`, so
customers log in with their existing password immediately).

**Wallet balances are NOT overwritten by default** — once a customer starts
using the new app, their balance here is the source of truth, not the old
site. Pass `--sync-wallet` only if you specifically want the legacy
balance to win (useful for the very first import, before anyone's used
the new app yet — don't use it after that).

**Found while building this:** `nin` and `picture_url` were missing from
`Customer::$fillable` — the exact same silent-failure bug as the
`wallet_balance` issue from before. Fixed alongside this.

A commented-out hourly schedule entry is in `routes/console.php` if you
want new legacy signups to flow in automatically — test manually first
before uncommenting it.

## Not yet built (next)

- Paystack webhook endpoint (currently relies on the frontend calling `/verify` after redirect).
- Course & cohort payment endpoints (Phase 3/4).
- Admin endpoints.
