<?php

namespace App\Services;

use App\Exceptions\DuplicateTransactionException;
use App\Repositories\TransactionRepository;
use App\Exceptions\ConcurrentRequestException;
use Illuminate\Support\Facades\Cache;

class IdempotencyService
{
    protected TransactionRepository $transactionRepository;

    public function __construct()
    {
        $this->transactionRepository = new TransactionRepository();
    }

    /**
     * Process request with idempotency guarantee
     */
    public function checkIdempotent(string $idempotencyKey) //,$sleep = false
    {
        $existing = $this->transactionRepository->getByKey('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            throw new DuplicateTransactionException("Duplicate transaction");
        }
//        if ($sleep) {
//            Log::info('sleep');
//            sleep(70);
//
//        }

        $lockKey = "idempotency:{$idempotencyKey}";
        $lock = Cache::lock($lockKey, 100);

        if (!$lock->get()) {
            throw new ConcurrentRequestException(
                "Another request with the same idempotency key is being processed"
            );
        }

        try {
            $existing = $this->transactionRepository->getByKey('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                throw new DuplicateTransactionException("Duplicate transaction");
            }
            return true;

        } finally {
            $lock->release();
        }
    }
}
