<?php
namespace App\Exceptions;

use Exception;

class ConcurrentRequestException extends Exception
{
    protected $code = 429;

    public function __construct($message = "Concurrent request in progress", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code ?: $this->code, $previous);
    }
}
