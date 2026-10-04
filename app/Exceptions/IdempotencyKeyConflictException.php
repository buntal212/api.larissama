<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyKeyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Idempotency-Key sudah digunakan untuk payload berbeda.');
    }
}
