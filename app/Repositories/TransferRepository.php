<?php
namespace App\Repositories;

use App\Models\Transfer;

class TransferRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Transfer::class);
    }


}
