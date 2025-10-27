<?php

namespace App\Exceptions;

use Exception;

class InvalidPaymentMilestoneException extends Exception
{
    protected $message = 'Invalid payment milestone operation';

    public function __construct(string $message = null, int $code = 0, Exception $previous = null)
    {
        if ($message !== null) {
            $this->message = $message;
        }

        parent::__construct($this->message, $code, $previous);
    }
}
