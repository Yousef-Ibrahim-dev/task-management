# Task Management API

REST API built with Laravel 12 and PHP 8.3, running on MySQL 8.4 and Redis under
Docker via Laravel Sail.

Architecture and conventions: [docs/architecture.md](docs/architecture.md).

No domain endpoints are implemented yet — this repository currently contains the
project foundation only.

## Requirements

- Docker Desktop
- Composer (for the first install only)
- PHP 8.3 in the container; the host only needs Composer

## Setup

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

The API is served at `http://localhost`.

Adding `alias sail='./vendor/bin/sail'` to your shell shortens the commands below.

## Stack

| Service | Image          | Port |
| ------- | -------------- | ---- |
| app     | `sail-8.3/app` | 80   |
| mysql   | `mysql:8.4`    | 3306 |
| redis   | `redis:alpine` | 6379 |

## Commands

```bash
sail composer lint       # apply Pint (Laravel preset)
sail composer lint:test  # check style, write nothing
sail composer analyse    # PHPStan level 6 (Larastan)
sail composer test       # test suite
sail composer check      # style, analysis and tests
```

## Architecture in one line

`Controller → Service → Repository → Model`. Controllers hold no business logic
and run no queries, services hold the business rules and run no queries,
repositories own every Eloquent call and never hand a query builder back out.
Form Requests validate, Policies authorize, API Resources format.

## API

Base path: `/api/v1`. Versions are registered in `routes/api.php`; v1 endpoints
live in `routes/api/v1.php`.

Every response uses one envelope — success, error and pagination shapes are
documented in
[docs/architecture.md](docs/architecture.md#response-envelope). Errors are
produced centrally from thrown exceptions, so status codes and body shape are
consistent across every endpoint.

Authentication will use Laravel Sanctum bearer tokens.

### Health check

```bash
curl -i http://localhost/up      # 200 while the application is healthy
```

## Configuration

| Variable                    | Default | Meaning                                        |
| --------------------------- | ------- | ---------------------------------------------- |
| `API_PAGINATION_DEFAULT`    | 15      | page size when a request does not ask for one  |
| `API_PAGINATION_MAX`        | 100     | ceiling for a client supplied page size        |
| `API_RATE_LIMIT_PER_MINUTE` | 60      | requests per minute on `/api/v1`               |
| `SANCTUM_TOKEN_EXPIRATION`  | 1440    | token lifetime in minutes (24 hours)           |

The rate limiter is keyed by user id when authenticated and by client IP
otherwise. Exceeding it returns HTTP 429 in the standard error envelope.
