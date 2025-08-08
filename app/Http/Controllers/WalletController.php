<?php
namespace App\Http\Controllers;

use App\Http\Resources\BalanceResource;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    protected WalletService $walletService;
    protected TransactionService $transactionService;

    public function __construct(
        WalletService $walletService,
        TransactionService $transactionService,
    ) {
        $this->walletService = $walletService;
        $this->transactionService = $transactionService;
    }


    public function show(Request $request): JsonResponse
    {
        try {
            $wallet = $this->walletService->getWalletDetails($request->user()->wallet->id);

            return response()->json([
                'success' => true,
                'data' => new WalletResource($wallet),
            ]);

        } catch (\Exception $e) {
            return response()->json(new ErrorResource([
                'error' => 'Failed to fetch wallet details',
                'details' => $e->getMessage(),
            ]), 500);
        }
    }


    public function balance(Request $request): JsonResponse
    {
        try {
            $balance = $this->walletService->getBalance($request->user()->wallet->id);

            return response()->json([
                'success' => true,
                'data' => new BalanceResource($balance),
            ]);

        } catch (\Exception $e) {
            return response()->json(new ErrorResource([
                'error' => 'Failed to fetch balance',
                'details' => $e->getMessage(),
            ]), 500);
        }
    }

    public function transactions(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => 'sometimes|string|in:deposit,withdrawal,transfer',
            'status' => 'sometimes|string|in:pending,processing,completed,failed',
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:10|max:100',
        ]);

        $transactions = $this->transactionService->getUserTransactions(
            $request->user()->id,
            $filters
        );

        $perPage = $filters['per_page'] ?? 20;
        $paginated = $transactions->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => TransactionResource::collection($paginated),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }


}
