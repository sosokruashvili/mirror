<?php

namespace App\Http\Requests;

use App\Models\SafeTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SafeTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(array_keys(SafeTransfer::directions()))],
            'status' => ['required', Rule::in(array_keys(SafeTransfer::statuses()))],
            'amount_gel' => 'required|numeric|min:0.01|max:999999999.99',
            'description' => 'nullable|string|max:5000',
            'transferred_at' => 'required|date',
        ];
    }

    public function attributes(): array
    {
        return [
            'direction' => __('safe.attributes.direction'),
            'status' => __('safe.attributes.status'),
            'amount_gel' => __('safe.attributes.amount_gel'),
            'description' => __('safe.attributes.description'),
            'transferred_at' => __('safe.attributes.transferred_at'),
        ];
    }
}
