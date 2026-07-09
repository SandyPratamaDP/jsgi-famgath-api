# 🎫 jsgi-famgath-api

REST API backend for the **JSGI Family Gathering 2026** event management system. Handles employee data, transport assignments, bus manifests, ticket PDF/image generation and regeneration, ticket email delivery, gate-scanner attendance, and wahana (Sea World / Samudera Ancol) check-ins.

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| 🧱 Framework | Laravel 13 |
| 🐘 Runtime | PHP 8.3+ |
| 🐘 Database | PostgreSQL |
| 🔑 Auth | Laravel Sanctum (bearer tokens) |
| 📄 PDF Generation | barryvdh/laravel-dompdf |
| 🖼️ PDF → PNG | Imagick + Ghostscript |
| 🔳 QR Codes | endroid/qr-code |
| 📊 Excel Import | phpoffice/phpspreadsheet |
| ⏳ Queue | Laravel queue (`database` driver in production) |

## ✅ Requirements

- PHP >= 8.3 with extensions: `pdo_pgsql`, `mbstring`, `xml`, `zip`, `imagick`, `gd`
- 👻 **Ghostscript** (`gs` binary) on `PATH` — Imagick shells out to it to rasterize the ticket PDF into a PNG
- Composer
- PostgreSQL
- ⚙️ A running queue worker — see "Queue worker" section below

## 🚀 Getting Started

```bash
# 1. Install dependencies
composer install

# 2. Configure environment
cp .env.example .env
# Edit .env — fill in FAMGATH_DB_HOST, FAMGATH_DB_DATABASE, FAMGATH_DB_USERNAME, FAMGATH_DB_PASSWORD

# 3. Generate app key
php artisan key:generate

# 4. Run migrations
php artisan migrate

# 5. Seed the two login accounts (panitia + eo)
php artisan db:seed --class=UserSeeder

# 6. Start the API
php artisan serve --host=0.0.0.0 --port=8000

# 7. Start the queue worker (separate process — see below)
php artisan queue:work
```

## ⚙️ Queue worker — important

Ticket PDF/PNG generation (`GenerateEmployeeTicketFiles`) and ticket email delivery (`SendEmployeeTicketEmail`) are dispatched as **queued jobs**, not run inline. Nothing gets generated or sent unless a worker is actively consuming the queue:

```bash
php artisan queue:work
```

🚨 In production this must run under a process supervisor (Supervisor, systemd, etc.) so it survives crashes and reboots — if the worker dies, uploads/regenerate actions will appear to "hang" with tickets stuck showing as not-yet-generated, since jobs just pile up in the `jobs` table until a worker comes back. Set `QUEUE_CONNECTION=database` (not `sync`) so jobs actually queue instead of running synchronously in the request.

## 🔐 Environment Variables

| Variable | Description |
|---|---|
| `APP_KEY` | Laravel application key (auto-generated) |
| `FAMGATH_DB_HOST` / `FAMGATH_DB_PORT` | PostgreSQL host/port |
| `FAMGATH_DB_DATABASE` | Database name |
| `FAMGATH_DB_USERNAME` / `FAMGATH_DB_PASSWORD` | Database credentials |
| `CORS_ALLOWED_ORIGINS` | Comma-separated list of allowed frontend origins |
| `QUEUE_CONNECTION` | Use `database` in production; jobs won't queue with `sync` |
| `NOTIFY_SERVICE_URL` / `NOTIFY_SERVICE_API_KEY` / `NOTIFY_SENDER_EMAIL` | Outbound ticket emails go through an internal Notify HTTP API (not SMTP) — see `NotificationService` |

## 🔑 Auth & Roles

Login (`POST /api/v1/auth/login`) issues a Sanctum bearer token valid for **12 hours**. Concurrent sessions are allowed — logging in again (another tab, another device) does not invalidate a still-active token elsewhere. Every request must send `Authorization: Bearer <token>` and `Accept: application/json`.

Two roles, checked by `role:<role>` middleware:

| Role | Access |
|---|---|
| 🛡️ `panitia` | Everything — employee data, Excel import, ticket generation/regeneration, blast email, Ancol QR management |
| 🎟️ `eo` | Gate scanner + wahana check-in only (read employee data, switch transport, check in to Sea World/Samudera) |

## 📡 API Endpoints

Base path: `/api`

### 🔐 Auth

| Method | Endpoint | Access |
|---|---|---|
| `POST` | `/v1/auth/login` | Public |
| `POST` | `/v1/auth/logout` | Any authenticated user |
| `GET` | `/v1/auth/me` | Any authenticated user |

### 👥 Employees — EO + Panitia

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/v1/employees` | List all employees |
| `GET` | `/v1/employees/search?query=` | Search by name |
| `PATCH` | `/v1/employees/{id}` | Update transport type/vehicles/passenger counts/additional participants & vehicles/under-2 flag. Automatically clears the cached ticket and re-queues generation whenever a ticket-relevant field actually changes |

### 🛡️ Employees — Panitia only

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/v1/import-employees` | Import employees from an `.xlsx`/`.xls` file; auto-(re)generates tickets for every eligible employee |
| `GET` | `/v1/tickets/blank` | Download a blank printable ticket form (manual walk-in use, not tied to an employee) |
| `POST` | `/v1/employees/blast-email` | ✉️ Email tickets to every eligible employee who hasn't received one yet |
| `POST` | `/v1/employees/{id}/send-email` | ✉️ (Re)send one employee's ticket email |
| `POST` | `/v1/employees/regenerate-tickets` | 🔄 Force-regenerate every eligible employee's ticket PDF/PNG in the background |
| `POST` | `/v1/employees/{id}/regenerate-ticket` | 🔄 Force-regenerate one employee's ticket PDF/PNG |
| `GET` | `/v1/employees/{id}/pdf` | ⬇️ Download one employee's ticket PDF |
| `GET` | `/v1/employees/{id}/image` | ⬇️ Download one employee's ticket as a PNG image |
| `GET` | `/v1/employees/{id}/qr` | ⬇️ Download one employee's personal QR code |
| `POST` | `/v1/ancol-qr/{category}` | 🔳 Upload the Ancol gate-entry QR for a category (`local`, `expat`, `operational`) |

💡 Only these employees ever get an individual ticket file: `private_car` and `operational` transport types, plus the designated PIC for a bus (`is_pic_bus`) — regular bus riders share their PIC's manifest ticket and have no `pdf_filename` of their own.

### 🎢 Wahana check-in — EO + Panitia

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/v1/wahana/search?query=` | Search employee for check-in |
| `GET` | `/v1/wahana/{code}` | Look up by QR code or manual code |
| `POST` | `/v1/wahana/{employee}/checkin` | Check in to `sea_world` or `samudera`; each is one-time-use per employee |

### 🔳 Ancol gate-entry QR

| Method | Endpoint | Access |
|---|---|---|
| `GET` | `/v1/ancol-qr/{category}` | EO + Panitia — fetch the gate-entry QR image for `local`/`expat`/`operational` |

## 🎟️ Ticket generation & regeneration

Each ticket-eligible employee has a `pdf_filename` column that's `null` until their PDF/PNG has been rendered. The `GenerateEmployeeTicketFiles` job renders and writes both files to `storage/app/public/tickets/`, then sets `pdf_filename`. Anything that changes ticket-relevant data (`Employee::TICKET_RELEVANT_FIELDS`) — an Excel re-import, an admin's manual field edit, or a regenerate action — nulls `pdf_filename` first and re-dispatches the job, so the file is always rebuilt from current data rather than served stale.

🚫 Ticket downloads are sent with `Cache-Control: no-store, must-revalidate` for the same reason: without it, browsers can keep serving a pre-edit cached download for hours after a regenerate.

## 📁 Project Structure

```text
app/
  Http/
    Controllers/      # API controllers
    Middleware/        # RoleMiddleware (panitia/eo gating)
  Jobs/                # GenerateEmployeeTicketFiles, SendEmployeeTicketEmail
  Models/              # Eloquent models
  Services/            # Import, PDF rendering, QR, auth, login logging, notifications
database/
  migrations/          # Schema definitions
  seeders/             # UserSeeder (panitia/eo accounts)
routes/
  api.php              # Route definitions
```

## 🔗 Related

- 🖥️ **Frontend (Gate Scanner, Wahana Scanner & Admin UI):** [jsgi-famgath-ui](https://github.com/SandyPratamaDP/jsgi-famgath-ui)
