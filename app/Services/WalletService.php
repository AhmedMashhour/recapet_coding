<?php

namespace App\Services;

use App\Exceptions\WalletNotFoundException;
use App\Repositories\WalletRepository;
use App\Traits\HasMoney;

class WalletService extends CrudService
{
    use HasMoney;

    protected WalletRepository $walletRepository;

    public function __construct()
    {
        parent::__construct('Wallet');
        $this->walletRepository = new WalletRepository();
    }

    public function getWalletDetails(int $walletId)
    {
        $wallet = $this->walletRepository->getById($walletId, ['user']);

        if (!$wallet) {
            throw new WalletNotFoundException("Wallet not found");
        }

        return $wallet;
    }

    public function getBalance(int $walletId): array
    {
        $wallet = $this->walletRepository->getById($walletId);

        if (!$wallet) {
            throw new WalletNotFoundException("Wallet not found");
        }


        return [
            'wallet_balance' => $wallet->balance,
            'formatted_balance' => $this->formatMoney($wallet->balance)
        ];
    }

}
