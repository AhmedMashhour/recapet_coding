<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\WalletRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService extends CrudService
{
    protected UserRepository $userRepository;
    protected WalletRepository $walletRepository;

    public function __construct()
    {
        parent::__construct('User');
        $this->userRepository = new UserRepository();
        $this->walletRepository = new WalletRepository();
    }

    public function register(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Create user
            $user = $this->userRepository->createWithHashedPassword($data);

            // Create wallet
            $this->walletRepository->create([
                'user_id' => $user->id,
                'wallet_number' => $this->walletRepository->generateUniqueWalletNumber(),
                'balance' => 0,
                'status' => 'active',
                'version' => 0
            ]);


            return $user->load('wallet');
        });
    }


    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }


        return [
            'user' => $user->load('wallet'),
            'token' => $user->createToken('auth-token')->plainTextToken
        ];
    }

    public function logout($user): void
    {
        $user->currentAccessToken()->delete();

    }

    public function getProfile(int $userId)
    {
        return $this->userRepository->getById($userId, ['wallet']);
    }
}
