<?php

namespace App\Http\Controllers;

use App\Exceptions\DuplicateTransactionException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletLockedException;
use App\Exceptions\WalletNotFoundException;
use App\Http\Requests\DepositRequest;
use App\Http\Requests\TransferRequest;
use App\Http\Requests\WithdrawalRequest;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\TransactionResource;
use App\Services\FeeCalculatorService;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;
    protected FeeCalculatorService $feeCalculator;
    protected TransferService $transferService;


    public function __construct(
        TransactionService   $transactionService,
        TransferService      $transferService,
        FeeCalculatorService $feeCalculator
    )
    {
        $this->transactionService = $transactionService;
        $this->feeCalculator = $feeCalculator;
        $this->transferService = $transferService;


    }

    public function deposit(DepositRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $validated['wallet_id'] = auth()->user()->wallet->id;
            $validated['idempotency_key'] = $request->getIdempotencyKey();


            $transaction = $this->transactionService->deposit($validated);
//            $transaction = null;
            return response()->json([
                'success' => true,
                'message' => 'Deposit successful',
                'data' => new TransactionResource($transaction),
                'new_balance' => auth()->user()->wallet->fresh()->balance,
            ], 201);

        }catch (WalletLockedException $e) {
            return response()->json(new ErrorResource([
                'error' => 'Wallet temporarily locked',
                'code' => 'WALLET_LOCKED',
                'details' => 'Another transaction is in progress. Please try again in a moment.',
            ]), 423);
        } catch (DuplicateTransactionException $e) {
            return response()->json(new ErrorResource([
                'error' => 'Duplicate transaction',
                'code' => 'DUPLICATE_TRANSACTION',
                'details' => 'This transaction has already been processed',
            ]), 409);

        }catch (\Exception $e) {
            return response()->json(new ErrorResource([
                'error' => 'Deposit failed',
                'details' => $e->getMessage(),
            ]), 500);
        }
    }

    public function withdraw(WithdrawalRequest $request): JsonResponse
    {

        try {
            $validated = $request->validated();
            $validated['wallet_id'] = auth()->user()->wallet->id;
            $validated['idempotency_key'] = $request->getIdempotencyKey();

            $transaction = $this->transactionService->withdraw($validated);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal successful',
                'data' => new TransactionResource($transaction),
                'new_balance' => auth()->user()->wallet->fresh()->balance,
            ], 201);

        }catch (DuplicateTransactionException $e) {
            return response()->json(new ErrorResource([
                'error' => 'Duplicate transaction',
                'code' => 'DUPLICATE_TRANSACTION',
                'details' => 'This transaction has already been processed',
            ]), 409);

        } catch (WalletLockedException $e) {
            return response()->json(new ErrorResource([
                'error' => 'Wallet temporarily locked',
                'code' => 'WALLET_LOCKED',
                'details' => 'Another transaction is in progress. Please try again in a moment.',
            ]), 423);
        }catch (InsufficientBalanceException $e) {
            return response()->json(new ErrorResource([
                'error' => 'Insufficient balance',
                'code' => 'INSUFFICIENT_BALANCE',
                'current_balance' => auth()->user()->wallet->balance,
            ]), 400);

        } catch (\Exception|\Throwable $e) {
            return response()->json(new ErrorResource([
                'error' => 'Withdrawal failed',
                'details' => $e->getMessage(),
            ]), 500);
        }
    }

    public function transfer(TransferRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Calculate fee preview
            $feeInfo = $this->feeCalculator->getFeeBreakdown($validated['amount']);

            $transaction = $this->transferService->executeTransfer(
                auth()->user()->wallet->id,
                $validated['receiver_wallet_number'],
                $validated['amount'],
                $request->getIdempotencyKey(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Transfer successful',
                'data' => new TransactionResource($transaction),
                'fee_breakdown' => $feeInfo,
                'new_balance' => auth()->user()->wallet->fresh()->balance,
            ], 201);

        }catch (WalletLockedException $e) {
            return response()->json(new ErrorResource([
                'error' => 'Wallet temporarily locked',
                'code' => 'WALLET_LOCKED',
                'details' => 'Another transaction is in progress. Please try again in a moment.',
            ]), 423);
        } catch (InsufficientBalanceException $e) {
            return response()->json(new ErrorResource([
                'error' => 'Insufficient balance',
                'code' => 'INSUFFICIENT_BALANCE',
                'current_balance' => auth()->user()->wallet->balance,
                'required_amount' => $validated['amount'] + $this->feeCalculator->calculateTransferFee($validated['amount']),
            ]), 400);

        } catch (WalletNotFoundException $e) {
            return response()->json(new ErrorResource([
                'error' => 'Wallet not found',
                'code' => 'WALLET_NOT_FOUND',
                'details' => $e->getMessage(),
            ]), 404);

        } catch (\Exception|\Throwable $e) {
            return response()->json(new ErrorResource([
                'error' => 'Transfer failed',
                'details' => $e->getMessage(),
            ]), 500);
        }
    }

    public function calculateFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:50000'],
        ]);

        $feeBreakdown = $this->feeCalculator->getFeeBreakdown($validated['amount']);

        return response()->json([
            'success' => true,
            'data' => $feeBreakdown,
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'string'],
        ]);
        $transaction = $this->transactionService->getTransactionById($validated['transaction_id']);

        if (!$transaction) {
            return response()->json(new ErrorResource([
                'error' => 'Transaction not found',
                'code' => 'TRANSACTION_NOT_FOUND',
            ]), 404);
        }

        // Check if user has access to this transaction
        $hasAccess = false;
        $userId = auth()->id();

        switch ($transaction->type) {
            case 'deposit':
                $hasAccess = $transaction->deposit->wallet->user_id === $userId;
                break;
            case 'withdrawal':
                $hasAccess = $transaction->withdrawal->wallet->user_id === $userId;
                break;
            case 'transfer':
                $hasAccess = $transaction->transfer->senderWallet->user_id === $userId
                    || $transaction->transfer->receiverWallet->user_id === $userId;
                break;
        }

        if (!$hasAccess) {
            return response()->json(new ErrorResource([
                'error' => 'Access denied',
                'code' => 'ACCESS_DENIED',
            ]), 403);
        }

        return response()->json([
            'success' => true,
            'data' => new TransactionResource($transaction),
        ]);
    }


}
