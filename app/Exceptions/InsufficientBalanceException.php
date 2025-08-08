<?php
namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    protected $code = 400;

    public function __construct($message = "Insufficient balance", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code ?: $this->code, $previous);
    }
}
