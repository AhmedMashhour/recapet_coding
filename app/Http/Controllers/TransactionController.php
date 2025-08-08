<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepositRequest;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(
        TransactionService $transactionService,
    )
    {
        $this->transactionService = $transactionService;
    }

    public function deposit(DepositRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $validated['wallet_id'] = auth()->user()->wallet->id;


            $transaction = $this->transactionService->deposit($validated);
//            $transaction = null;
            return response()->json([
                'success' => true,
                'message' => 'Deposit successful',
                'data' => new TransactionResource($transaction),
                'new_balance' => auth()->user()->wallet->fresh()->balance,
            ], 201);

        } catch (\Exception $e) {
            return response()->json(new ErrorResource([
                'error' => 'Deposit failed',
                'details' => $e->getMessage(),
            ]), 500);
        }
    }

}
