# KCALS — Project Guidelines

## Architecture

Layered, no-framework PHP 8+ app:

- **Root pages** (`dashboard.php`, `plan.php`, etc.) — entry points; include auth, call engine, render HTML
- **`includes/`** — cross-cutting helpers: `auth.php` (sessions, CSRF, guards), `lang.php` (i18n bootstrap), `header.php`/`footer.php`
- **`engine/`** — pure business logic: `calculator.php` (Mifflin-St Jeor, macros, zones), `meal_builder.php` (`MealBuilder` class)
- **`admin/`** — separate authenticated subsystem with its own `includes/admin_auth.php`, AJAX endpoints in `admin/ajax/`
- **`config/db.php`** — PDO singleton `getDB()`; never committed (use `db.php.example` as template)
- **`lang/`** — translation arrays (`en.php`, `el.php`); add new languages here

Every user-facing page must `require_once __DIR__ . '/includes/auth.php';` as its first action.

## Database

- **Always use PDO prepared statements** via `getDB()`. Never interpolate user data into SQL.
- Fetch mode is `PDO::FETCH_ASSOC`. `PDO::ATTR_EMULATE_PREPARES` is `false`.
- Catch `PDOException`, log with `error_log()`, and show a generic message — never expose DB internals.
- **Migrations**: add sequential files `sql/migrations/NNN_description.sql`; register the filename in `schema_migrations` with `INSERT IGNORE`. Use `IF NOT EXISTS`, `INSERT IGNORE`, and `ON DUPLICATE KEY UPDATE` to keep migrations idempotent.

## Auth & Security

- Guard pages with `requireLogin()` (user) or `requireAdmin()` (admin) — both exit on failure.
- Sessions are started in `auth.php` with `cookie_httponly => true` and `cookie_samesite => Strict`.
- **All POST forms must include a hidden CSRF token** and verify it before processing:
  ```php
  // In form:  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  // In handler:
  if (!verifyCsrf($_POST['csrf_token'] ?? '')) { /* reject */ }
  ```
- Passwords: `password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12])` / `password_verify()`.
- Regenerate session ID on login: already done inside `loginUser()`.

## Internationalization

- Use `__('key')` for every user-visible string. Never hard-code English text in pages.
- Add new keys to **both** `lang/en.php` and `lang/el.php`.
- Language is stored in `$_SESSION['lang']`; switch via `lang/set.php`.

## Code Style

- **PHP type hints and return types** on every function signature:
  ```php
  function calculateBMR(float $weightKg, int $heightCm, int $ageYears, string $gender): float { … }
  ```
- **Naming**: `camelCase` functions, `PascalCase` classes, `snake_case` local variables and DB columns, `SCREAMING_SNAKE_CASE` constants.
- **Indentation**: 4 spaces. No tabs.
- Section separators: `// ======== SECTION NAME ========`
- PHPDoc blocks on public/named functions in `engine/` and `includes/`.

## Error Handling

- Validation errors: collect into `$errors = []`, translate via `__()`, and render in the template — do not exit early.
- Fatal/unexpected failures: `error_log()` full details; surface only a generic translated message to the user.

## Build & Setup

```bash
# Database (XAMPP)
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS kcals CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql.exe -u root kcals < sql/schema.sql

# Config
copy config\db.php.example config\db.php
# Edit config/db.php with local credentials
```

Access via `http://localhost/kcals/`. No build step — pure PHP, no Composer, no bundler.
