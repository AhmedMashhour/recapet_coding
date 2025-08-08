<?php
namespace App\Exceptions;

use Exception;

class DuplicateTransactionException extends Exception
{
    protected $code = 409;

    public function __construct($message = "Duplicate transaction", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code ?: $this->code, $previous);
    }
}
