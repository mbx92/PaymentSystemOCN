# PaymentSystemOCN

Laravel 11 + Vue 3 (Inertia) ERP system for accounting & purchasing.

## Tech Stack
- **Backend**: PHP 8.2, Laravel 11, Spatie Permissions, Laravel Excel, DOMPDF
- **Frontend**: Vue 3, Inertia.js, Pinia, Tailwind CSS 4, DaisyUI 5, Chart.js
- **Database**: MySQL (dev), SQLite (testing)
- **Testing**: PHPUnit 10 (Feature & Unit tests)

## Conventions
- Models: `app/ERP/{Module}/Models/`
- Controllers: `app/Http/Controllers/`
- Services: `app/Services/`
- Vue pages: `resources/js/Pages/ERP/{Module}/`
- Routes: `routes/web.php`
- Migrations: standard Laravel `database/migrations/`

## Tests
- `php artisan test` — run all tests
- `php artisan test --filter={TestName}` — run specific test
- `./vendor/bin/phpunit` — direct PHPUnit

## Lint
- `./vendor/bin/pint` — Laravel Pint (PSR-12)

## Dev server
- `npm run start` — runs Vite + `php artisan serve` concurrently
- `php artisan serve` — backend only
- `npm run dev` — Vite only
