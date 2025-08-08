<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class IdempotentRequest extends FormRequest
{

    public function getIdempotencyKey(): ?string
    {
        return $this->header('X-Idempotency-Key');
    }


    protected function validateIdempotencyKey(): array
    {
        $key = $this->getIdempotencyKey();
        if (is_null($key)) {
            return ['X-Idempotency-Key' => 'Request Key is required.'];
        }

        if ($key && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $key)) {
            return ['X-Idempotency-Key' => 'Invalid idempotency key format. Must be a valid UUID.'];
        }

        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $errors = $this->validateIdempotencyKey();
            foreach ($errors as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        });
    }
}
