<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'wallet_balance' => $this->resource['wallet_balance'],
            'formatted_wallet_balance' => '$' . number_format($this->resource['wallet_balance'], 2),
        ];
    }
}
