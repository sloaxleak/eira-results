<?php

namespace App\Services\Documents;

use RuntimeException;

class WorkflowException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
