# jsgi-famgath-api

REST API backend for the **JSGI Family Gathering 2026** event management system. Handles employee data, transport assignments, bus manifests, attendance tracking, and PDF ticket generation.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 |
| Runtime | PHP 8.2+ |
| Database | PostgreSQL |
| PDF Generation | barryvdh/laravel-dompdf |
| Excel Import | phpoffice/phpspreadsheet |

## Requirements

- PHP >= 8.2 with extensions: `pdo_pgsql`, `mbstring`, `xml`, `zip`
- Composer
- PostgreSQL

## Getting Started

```bash
# 1. Install dependencies
composer install

# 2. Configure environment
cp .env.example .env
# Edit .env — fill in DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 3. Generate app key
php artisan key:generate

# 4. Run migrations
php artisan migrate

# 5. Start development server
php artisan serve --host=0.0.0.0 --port=8000
```

## Environment Variables

| Variable | Description |
|---|---|
| `APP_KEY` | Laravel application key (auto-generated) |
| `DB_HOST` | PostgreSQL host |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` / `DB_PASSWORD` | Database credentials |
| `CORS_ALLOWED_ORIGINS` | Comma-separated list of allowed frontend origins |

## API Endpoints

Base path: `/api/v1`

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/employees` | List all employees with transport & attendance data |
| `GET` | `/employees/search?query=` | Search employee by name or NIK |
| `PATCH` | `/employees/{id}` | Update employee (transport type, vehicles, attendance) |
| `POST` | `/employees/bulk-pdf` | Generate bulk PDF tickets for private car & PIC bus |
| `POST` | `/import-employees` | Import employees via `.xlsx` file |

## CORS

Allowed origins are set in `.env`:

```env
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://your-frontend.vercel.app
```

## Project Structure

```
app/
  Http/
    Controllers/      # API controllers
  Models/             # Eloquent models
database/
  migrations/         # Schema definitions
routes/
  api.php             # Route definitions
```

## Related

- **Frontend (Gate Scanner & Admin UI):** [jsgi-famgath-ui](https://github.com/SandyPratamaDP/jsgi-famgath-ui)
