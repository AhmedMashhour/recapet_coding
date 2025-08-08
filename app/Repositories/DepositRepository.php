<?php

namespace App\Repositories;

use App\Models\Deposit;

class DepositRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Deposit::class);
    }

}
