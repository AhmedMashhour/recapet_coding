<?php

return [
    'locking' => [
        // Timeout for wallet locks in seconds
        'timeout' => env('WALLET_LOCK_TIMEOUT', 30),

        // Maximum retry attempts when wallet is locked
        'max_retries' => env('WALLET_LOCK_MAX_RETRIES', 3),

        // Delay between retries in milliseconds
        'retry_delay_ms' => env('WALLET_LOCK_RETRY_DELAY', 500),
    ],

    'concurrency' => [
        // Enable strict wallet locking
        'strict_locking' => env('WALLET_STRICT_LOCKING', true),

        // Enable row-level locking in database
        'use_row_locks' => env('WALLET_USE_ROW_LOCKS', true),
    ],
];
