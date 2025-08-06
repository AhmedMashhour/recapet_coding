<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRepository extends Repository
{
    public function __construct()
    {
        parent::__construct(User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->getModel->where('email', $email)->first();
    }

    public function createWithHashedPassword(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        return $this->create($data);
    }


}
