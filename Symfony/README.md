# Symfony contacts API and form

This project provides:
- a web contact form at `/contacts`
- a JWT-protected API endpoint to create contacts at `POST /api/contacts`
- a token endpoint at `POST /api/auth/token`

## Prerequisites

- PHP 8.2+
- Composer 2+
- Required PHP extensions for this project and tests:
  - `pdo`
  - `pdo_sqlite` (used by the test suite)
  - `openssl`

## Setup from scratch

1. Install dependencies:

```bash
composer install
```

2. Create local environment overrides:

```bash
cp .env .env.local
```

3. Set local values in `.env.local` (minimum):

```dotenv
APP_SECRET=replace-with-random-string

# API auth (used by /api/auth/token)
API_AUTH_USERNAME=api-user
API_AUTH_PASSWORD=replace-with-strong-password
API_JWT_SECRET=replace-with-long-random-secret
API_JWT_TTL=3600

# Optional local DB override (example SQLite)
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"
```

4. Create database schema:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

5. Run the app:

```bash
php -S 127.0.0.1:8000 -t public
```

## API usage quick start

### 1) Get JWT token

`POST /api/auth/token`

Request body:

```json
{
  "username": "api-user",
  "password": "replace-with-strong-password"
}
```

Successful response:

```json
{
  "token": "<jwt>"
}
```

### 2) Create contact

`POST /api/contacts`

Headers:
- `Authorization: Bearer <jwt>`
- `Content-Type: application/json`

Request body:

```json
{
  "firstName": "Jane",
  "lastName": "Doe",
  "email": "jane.doe@example.com",
  "active": true
}
```

## Running tests

Run all tests:

```bash
php bin/phpunit --testdox
```

The suite is configured to use a dedicated test SQLite database (`var/data_test.db`) via `tests/bootstrap.php`.

## What the 3 API tests cover (and why they matter)

File: `tests/Controller/ContactApiControllerTest.php`

1. `POST /api/contacts returns 201 and persists contact data when bearer token and payload are valid`
   - Verifies the happy path from authenticated request to persisted entity and response payload.
   - Important because it protects the main business workflow.

2. `POST /api/contacts returns 422 with field-level validation errors when firstName/lastName are blank and email format is invalid`
   - Verifies invalid inputs are rejected with explicit field-level error details.
   - Important because it prevents bad data persistence and guarantees clear client feedback.

3. `POST /api/contacts returns 401 with a detailed auth reason when bearer token format is invalid`
   - Verifies protected endpoint behavior when authentication fails.
   - Important because it confirms the API does not allow unauthenticated access and returns actionable auth errors.

## Things that would be done with more time

1. Database-driven JWT lifecycle management
   - Move from environment-only/shared credential flow to a database-driven token model with full lifecycle support.
   - Add support for token status tracking (issued, consumed, expired, revoked), explicit expiry timestamps (for example `expires_at`), one-time-use enforcement, and cleanup of consumed/expired tokens.
   - Enforce token expiry at request time and treat expired tokens as invalid even if the JWT payload is otherwise well-formed.
   - Use this to support secure email verification links before enabling certain actions, so a syntactically valid email is not treated as implicitly trusted.

2. Role-based manager ownership and notifications
   - Replace the single manager email value in `.env` with proper users/roles management.
   - Introduce at minimum `users`, `roles`, and `user_roles` tables.
   - Notify all users assigned a manager role, allowing multiple managers as workload grows.
   - Add administration UI for managing users, roles, and role assignments.

3. Active flag usage strategy
   - The `active` field is currently stored but not used for access/control workflows.
   - Implement clear behavior where inactive records/users are blocked from specific operations based on business rules.

4. UI and UX rework
   - The current UI is intentionally minimal and prioritizes logic and flow validation.
   - Rework the frontend into a fuller user journey (home page to signup/create-contact flow).
   - Apply a stronger visual identity and consistent design system across pages.

5. Persistence-first validation/session approach
   - Prefer database-backed validation and token/session lifecycle records for critical auth flows.
   - Avoid relying on cache/cookies for core token lifecycle state (except for non-critical convenience data), so behavior is resilient across deployments and cache clears.
   - Keep signed-in convenience context lightweight while critical auth state remains durable and auditable.


