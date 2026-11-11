<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Services\PaymentAllocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'order_ids' => ['nullable', 'array'],
            'order_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('orders', 'id')->where('client_id', (int) $this->input('client_id')),
            ],
            'method' => ['required', Rule::in(['Cash', 'Transfer', 'Terminal', 'PM Transfer'])],
            'type' => ['required', Rule::in(array_keys(Payment::types()))],
            'status' => ['required', Rule::in(['Paid', 'Pending'])],
            'currency_rate' => 'required|numeric|min:0',
            'amount_gel' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'file' => 'nullable|file|max:65536',
        ];
    }

    /**
     * A payment that covers several orders has to pay them all off. A short one
     * would leave every one of them unpaid (see PaymentAllocation), so it is
     * refused. One order may still be paid in part.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $amount = (float) $this->input('amount_gel');
            $needed = app(PaymentAllocation::class)->shortfall(
                (int) $this->input('client_id'),
                (array) $this->input('order_ids', []),
                $amount,
                $this->route('id') ? (int) $this->route('id') : null
            );

            if ($needed !== null) {
                $validator->errors()->add('amount_gel', __('payment.messages.multi_order_short', [
                    'amount' => number_format(round($amount), 2),
                    'needed' => number_format($needed, 2),
                ]));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'client',
            'order_ids' => 'orders',
            'order_ids.*' => 'order',
            'method' => 'payment method',
            'type' => 'payment type',
            'status' => 'status',
            'currency_rate' => 'currency rate',
            'amount_gel' => 'amount (GEL)',
            'payment_date' => 'payment date',
        ];
    }
}
