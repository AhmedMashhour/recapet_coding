<?php
namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->register($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => new UserResource($user),
                    'wallet_number' => $user->wallet->wallet_number,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->userService->authenticate(
            $request->email,
            $request->password
        );

        if (!$result) {
            return response()->json([
                'error' => 'Invalid credentials',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'token_type' => 'Bearer',
            ],
        ]);
    }


    public function logout(Request $request): JsonResponse
    {
        $this->userService->logout($request->user());

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $this->userService->getProfile($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $newToken = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $newToken,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
