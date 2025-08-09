<?php
namespace App\Exceptions;

use Exception;

class WalletLockedException extends Exception
{
    protected $code = 423;

    public function __construct($message = "Wallet temporarily locked", $code = 423, Exception $previous = null)
    {
        parent::__construct($message, $code ?: $this->code, $previous);
    }
}
