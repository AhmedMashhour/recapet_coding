<?php

namespace App\Repositories;

use App\Models\Wallet;

class WalletRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(Wallet::class);
    }

    public function generateUniqueWalletNumber(): string
    {
        do {
            $number = 'W-' . rand(10000000, 99999999);
        } while ($this->getModel->where('wallet_number', $number)->exists());

        return $number;
    }


}
