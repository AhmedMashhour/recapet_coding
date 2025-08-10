<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CreateBalanceSnapshot;

class CreateBalanceSnapshotCommand extends Command
{
    protected $signature = 'wallet:snapshot';

    protected $description = 'Create a balance snapshot of all wallets';

    public function handle()
    {
        CreateBalanceSnapshot::dispatch();
        return 0;
    }

}
