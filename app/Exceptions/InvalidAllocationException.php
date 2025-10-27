<?php

namespace App\Exceptions;

use Exception;

class InvalidAllocationException extends Exception
{
    protected $message = 'Invalid component allocation operation';

    public function __construct(string $message = null, int $code = 0, Exception $previous = null)
    {
        if ($message !== null) {
            $this->message = $message;
        }

        parent::__construct($this->message, $code, $previous);
    }
}
