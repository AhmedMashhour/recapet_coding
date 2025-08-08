<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'type' => $this->type,
            'status' => $this->status,
            'amount' => $this->amount,
            'formatted_amount' => '$' . number_format($this->amount, 2),
            'fee' => $this->fee,
            'formatted_fee' => '$' . number_format($this->fee, 2),
            'total_amount' => $this->amount + $this->fee,
            'formatted_total' => '$' . number_format($this->amount + $this->fee, 2),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toDateTimeString(),
            'completed_at' => $this->completed_at?->toDateTimeString(),
        ];
    }


}
