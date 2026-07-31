<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidCredentialsException extends RuntimeException
{
    public function __construct(string $message = 'The provided credentials are incorrect.')
    {
        parent::__construct($message);
    }
}
