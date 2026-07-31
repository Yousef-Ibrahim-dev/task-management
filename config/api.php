<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | "default" is the page size used when a request does not ask for one, and
    | "max" is the ceiling applied to a client supplied page size. Both are
    | enforced by BaseRepository::resolvePerPage() so no repository repeats them.
    |
    */

    'pagination' => [
        'default' => (int) env('API_PAGINATION_DEFAULT', 15),
        'max' => (int) env('API_PAGINATION_MAX', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Requests per minute allowed by the "api" limiter, which is applied to the
    | /api/v1 route group and keyed by user id when authenticated, client IP
    | otherwise.
    |
    */

    'rate_limit' => [
        'per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),
    ],

];
