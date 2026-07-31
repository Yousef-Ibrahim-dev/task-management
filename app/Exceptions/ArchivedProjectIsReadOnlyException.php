<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ArchivedProjectIsReadOnlyException extends RuntimeException
{
    public function __construct(string $message = 'An archived project cannot be modified.')
    {
        parent::__construct($message);
    }
}
