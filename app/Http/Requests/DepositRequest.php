<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends IdempotentRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:100000|regex:/^\d+(\.\d{1,2})?$/',
            'payment_method' => 'sometimes|string|in:bank_transfer,card,cash,paypal',
            'payment_reference' => 'sometimes|string|max:255',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Amount must have at most 2 decimal places.',
            'amount.min' => 'Minimum deposit amount is $0.01.',
            'amount.max' => 'Maximum deposit amount is $100,000.',
            'payment_method.in' => 'Invalid payment method selected.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => round((float) $this->amount, 2),
        ]);
    }
}
