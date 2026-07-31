<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class BaseApiController extends Controller
{
    use ApiResponse;
    use AuthorizesRequests;
}
