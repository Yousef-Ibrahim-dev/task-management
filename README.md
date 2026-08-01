# Task Management API

A REST API for managing projects and the tasks inside them, built with Laravel 12 and PHP 8.3.

Authentication uses Laravel Sanctum bearer tokens. Every project and task is owned by a user, and that ownership is enforced in the database query rather than checked afterwards — another user's data is indistinguishable from data that does not exist. The codebase follows a Controller → Service → Repository → Model flow, with repository interfaces bound in a service provider, and is covered by 297 feature and unit tests.

Included: authentication, a projects API, a nested tasks API with filtering and search, and a dashboard summary endpoint.

## Requirements

- Docker Desktop, or any compatible Docker runtime
- Composer (for the initial install only)
- PHP 8.3, MySQL 8.4 and Redis — all provided by the containers

Laravel Sail is the recommended setup; everything below assumes it.

## Installation

```bash
git clone <repository-url>
cd task-management
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

The API is then served at `http://localhost`, and `GET /up` is the health check.

If you would rather not install Composer on the host, run the install through a throwaway container first:

```bash
docker run --rm -v "$(pwd)":/opt -w /opt laravelsail/php83-composer:latest composer install
```

Adding `alias sail='./vendor/bin/sail'` to your shell shortens every command below.

## Environment

| Variable | Default | Meaning |
| --- | --- | --- |
| `DB_HOST` | `mysql` | Database host — matches the compose service name |
| `DB_PORT` | `3306` | Database port |
| `DB_DATABASE` | `laravel` | Database name |
| `DB_USERNAME` | `sail` | Database user |
| `DB_PASSWORD` | `password` | Database password (local only) |
| `REDIS_HOST` | `redis` | Redis host |
| `API_PAGINATION_DEFAULT` | `15` | Page size when a request does not ask for one |
| `API_PAGINATION_MAX` | `100` | Ceiling applied to a requested page size |
| `API_RATE_LIMIT_PER_MINUTE` | `60` | General limit on `/api/v1` |
| `API_REGISTER_RATE_LIMIT_PER_MINUTE` | `3` | Registration attempts per minute, per IP |
| `API_LOGIN_RATE_LIMIT_PER_MINUTE` | `5` | Login attempts per minute, per email + IP |
| `SANCTUM_TOKEN_EXPIRATION` | `1440` | Token lifetime in minutes (24 hours) |

`.env` is gitignored; `.env.example` holds working local defaults and no secrets.

## Demo credentials

| Email | Password |
| --- | --- |
| `demo@example.com` | `password` |
| `second@example.com` | `password` |

**Development and assessment use only. Never use these credentials in production.**
The seeder refuses to run when the application environment is `production`.

The second account exists so ownership isolation can be checked by hand: log in as each and confirm neither can see the other's projects.

## Architecture

```
Controller → Service → Repository Interface → Repository → Model
```

- **Controller** — reads the request, calls a service, returns a resource through the shared response layer. No queries, no business rules.
- **Service** — owns the business rules and coordinates repositories. Runs no queries of its own.
- **Repository** — owns every Eloquent call. Never returns a query builder, so database logic cannot leak upward.
- **Model** — schema, casts, relations.

Validation lives in Form Requests, authorization in Policies, output shaping in API Resources.

There is no generic base repository: its reads would be unscoped, and inheriting them onto a user-owned contract would publish a supported way to reach someone else's rows. Each domain repository declares its own contract instead, and every read states its ownership scope.

## API response format

Every response uses one envelope, produced in a single place and applied to errors as well as successes.

**Success**

```json
{ "success": true, "message": "Project retrieved successfully.", "data": { } }
```

**Validation error** — HTTP 422

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "name": ["The name field is required."] }
}
```

**Paginated** — `meta` and `links` sit beside `data`, never inside it

```json
{
  "success": true,
  "message": "Projects retrieved successfully.",
  "data": [],
  "meta": { "current_page": 1, "per_page": 15, "last_page": 4, "total": 52 },
  "links": { "first": "…?page=1", "last": "…?page=4", "prev": null, "next": "…?page=2" }
}
```

`DELETE` returns HTTP 204 with an empty body. Errors always carry an `errors` object, `{}` when there is nothing structured to report.

## Authentication

Register or log in, then send the returned token on every other request:

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Logging out revokes only the token that made the request, so other devices stay signed in. An unknown email and a wrong password produce the identical 422 response, so the API never confirms whether an address is registered.

## Endpoints

| Method | URI | Auth | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/auth/register` | – | Create an account and issue a token |
| POST | `/api/v1/auth/login` | – | Issue a token for existing credentials |
| POST | `/api/v1/auth/logout` | ✔ | Revoke the current token (204) |
| GET | `/api/v1/auth/me` | ✔ | Return the authenticated user |
| GET | `/api/v1/projects` | ✔ | List owned projects |
| POST | `/api/v1/projects` | ✔ | Create a project (201) |
| GET | `/api/v1/projects/{project}` | ✔ | Show one owned project |
| PUT/PATCH | `/api/v1/projects/{project}` | ✔ | Update a project |
| DELETE | `/api/v1/projects/{project}` | ✔ | Soft delete a project (204) |
| PATCH | `/api/v1/projects/{project}/archive` | ✔ | Set status to `archived` |
| PATCH | `/api/v1/projects/{project}/restore-status` | ✔ | Set status back to `active` |
| GET | `/api/v1/projects/{project}/tasks` | ✔ | List tasks in an owned project |
| POST | `/api/v1/projects/{project}/tasks` | ✔ | Create a task (201) |
| GET | `/api/v1/projects/{project}/tasks/{task}` | ✔ | Show one task |
| PUT/PATCH | `/api/v1/projects/{project}/tasks/{task}` | ✔ | Update a task |
| DELETE | `/api/v1/projects/{project}/tasks/{task}` | ✔ | Soft delete a task (204) |
| GET | `/api/v1/dashboard` | ✔ | Counters for the authenticated user |
| GET | `/up` | – | Framework health check |

`restore-status` changes a project's status; it does **not** restore a soft-deleted record.

## Filters

**Projects** — `GET /api/v1/projects`

| Parameter | Values |
| --- | --- |
| `status` | `active`, `completed`, `archived` |
| `page` | integer ≥ 1 |
| `per_page` | integer ≥ 1, clamped to `API_PAGINATION_MAX` |

**Tasks** — `GET /api/v1/projects/{project}/tasks`

| Parameter | Values |
| --- | --- |
| `status` | `todo`, `in_progress`, `done` |
| `priority` | `low`, `medium`, `high` |
| `search` | matches the task **title** only; trimmed, and ignored when blank |
| `page` | integer ≥ 1 |
| `per_page` | integer ≥ 1, clamped to `API_PAGINATION_MAX` |

Filters combine with AND. A value outside an enum returns 422 rather than being ignored.

```
GET /api/v1/projects/1/tasks?status=todo&priority=high&search=login
```

## Dashboard

`GET /api/v1/dashboard` returns eight counters for the authenticated user, computed in two aggregate queries.

| Counter | Meaning |
| --- | --- |
| `total_projects` | Owned projects that are not soft deleted |
| `active_projects` | Of those, status `active` |
| `completed_projects` | Of those, status `completed` |
| `archived_projects` | Of those, status `archived` |
| `total_tasks` | Tasks inside owned, non-deleted projects |
| `completed_tasks` | Of those, status `done` |
| `pending_tasks` | Of those, status other than `done` |
| `overdue_tasks` | Not `done`, with a due date before today |

Tasks under a soft-deleted project are excluded from every task counter. Tasks in an *archived* project are still counted — archived is a status, not a deletion.

### Seeded dashboard values

After `migrate --seed`, `demo@example.com` returns exactly:

```json
{
  "total_projects": 4,
  "active_projects": 2,
  "completed_projects": 1,
  "archived_projects": 1,
  "total_tasks": 14,
  "completed_tasks": 6,
  "pending_tasks": 8,
  "overdue_tasks": 3
}
```

`second@example.com` returns 2 projects and 3 tasks, so the two datasets are easy to tell apart.

## Testing and quality

```bash
sail composer test      # PHPUnit
sail composer lint      # Laravel Pint, applies fixes
sail composer analyse   # PHPStan level 6 (Larastan)
sail composer check     # style + analysis + tests
sail composer audit     # dependency advisories
```

Without the alias, prefix with `./vendor/bin/sail`. On a host with PHP 8.3 installed the same commands work with plain `composer`.

## Postman

`postman/` contains a collection and a local environment.

1. Postman → **Import** → select both files in `postman/`.
2. Select the **Task Management API — Local** environment.
3. Run **Authentication → Login Demo User**; the token is stored automatically.
4. Every other request uses that token; `Login Second User` fills `second_token` for isolation checks.

`base_url` defaults to `http://localhost`. Requests that create a project or task save the new id into the environment, so the folders can be run top to bottom.

## Design decisions

- **Ownership is a SQL condition, never a PHP comparison.** Reads are scoped by owner in the query, so another user's project or task returns **404**, not 403 — the API never reveals that a record exists.
- **Soft-deleted projects hide their tasks.** Task queries are anchored to a live parent project, so deleting a project makes its tasks unreachable without touching them.
- **Deleting a project does not cascade to its tasks.** A database cascade cannot fire while the parent row still exists; the query boundary does the work instead. A force delete does cascade.
- **Archived projects are read-only for tasks.** Listing and viewing work; create, update and delete return **409 Conflict**.
- **Completed projects stay editable.** Completing states the work is finished; archiving takes it out of the working set.
- **`completed_at` is managed by `TaskService`, never by the client.** Marking a task `done` stamps it, moving it back clears it, and an unrelated edit preserves it.
- **Seed dates are relative to seeding time**, so "overdue" and "due today" keep their meaning whenever the dataset is rebuilt.

## Submission contents

- Source code — `app/`, `bootstrap/`, `config/`, `routes/`
- Migrations — `database/migrations/`
- Factories and seeders — `database/factories/`, `database/seeders/`
- Postman collection and environment — `postman/`
- Tests — `tests/`
- This README
