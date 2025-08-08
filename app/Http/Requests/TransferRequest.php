<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Wallet;

class TransferRequest extends FormRequest
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
            'receiver_wallet_number' => [
                'required',
                'string',
                'exists:wallets,wallet_number',
                function ($attribute, $value, $fail) {
                    // Check if not sending to self
                    if (auth()->user()->wallet->wallet_number === $value) {
                        $fail('You cannot transfer funds to your own wallet.');
                    }

                    // Check if receiver wallet is active
                    $wallet = Wallet::where('wallet_number', $value)->first();
                    if ($wallet && $wallet->status !== 'active') {
                        $fail('You cannot transfer funds to receiver wallet.');
                    }
                },
            ],
            'amount' => 'required|numeric|min:0.01|max:50000|regex:/^\d+(\.\d{1,2})?$/',
            'description' => 'sometimes|string|max:255',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'receiver_wallet_number.exists' => 'Receiver wallet not found.',
            'amount.regex' => 'Amount must have at most 2 decimal places.',
            'amount.min' => 'Minimum transfer amount is $0.01.',
            'amount.max' => 'Maximum transfer amount is $50,000.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => round((float) $this->amount, 2),
        ]);
    }
}
