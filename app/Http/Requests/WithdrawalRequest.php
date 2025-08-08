<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
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
            'amount' => 'required|numeric|min:0.01|max:50000|regex:/^\d+(\.\d{1,2})?$/',
            'withdrawal_method' => 'sometimes|string|in:bank_transfer,card,cash',
            'withdrawal_reference' => 'sometimes|string|max:255',
        ];
    }


    public function messages(): array
    {
        return [
            'amount.regex' => 'Amount must have at most 2 decimal places.',
            'amount.min' => 'Minimum withdrawal amount is $0.01.',
            'amount.max' => 'Maximum withdrawal amount is $50,000.',
            'withdrawal_method.in' => 'Invalid withdrawal method selected.',
        ];
    }


    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => round((float) $this->amount, 2),
        ]);
    }
}
