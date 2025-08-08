<?php
namespace App\Exceptions;

use Exception;

class WalletNotFoundException extends Exception
{
    protected $code = 404;

    public function __construct($message = "Wallet not found", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code ?: $this->code, $previous);
    }
}
