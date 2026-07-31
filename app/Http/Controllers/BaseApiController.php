<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Traits\ApiResponse;

abstract class BaseApiController extends Controller
{
    use ApiResponse;
}
