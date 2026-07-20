<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'method' => ['required', Rule::in(['Cash', 'Transfer', 'Terminal', 'PM Transfer'])],
            'type' => ['required', Rule::in(array_keys(Payment::types()))],
            'status' => ['required', Rule::in(['Paid', 'Pending'])],
            'currency_rate' => 'required|numeric|min:0',
            'amount_gel' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'file' => 'nullable|file|max:10240',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'client',
            'order_id' => 'order',
            'method' => 'payment method',
            'type' => 'payment type',
            'status' => 'status',
            'currency_rate' => 'currency rate',
            'amount_gel' => 'amount (GEL)',
            'payment_date' => 'payment date',
        ];
    }
}
