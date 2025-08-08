<?php

namespace App\Repositories;

use App\Models\LedgerEntry;

class LedgerEntryRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(LedgerEntry::class);
    }


}
