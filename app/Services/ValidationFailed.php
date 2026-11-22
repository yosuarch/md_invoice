<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Thrown by services when input is invalid; carries field => message errors for the form.
 * Throwing inside Transaction::run() also rolls the transaction back.
 */
final class ValidationFailed extends RuntimeException
{
    /**
     * @param array<string, string> $errors field => message
     */
    public function __construct(public readonly array $errors, string $message = 'Validation failed.')
    {
        parent::__construct($message);
    }
}
