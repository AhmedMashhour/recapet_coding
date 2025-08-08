<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => false,
            'error' => $this->resource['error'] ?? 'An error occurred',
            'code' => $this->resource['code'] ?? 'ERROR',
            'details' => $this->resource['details'] ?? null,
        ];
    }
}
