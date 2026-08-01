<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseCorrectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'product_id' => 'required|exists:products,id',
            // Signed: negative writes stock off, positive adds it back. A zero
            // correction would be a no-op row, so it is rejected.
            'area' => 'required|numeric|not_in:0',
            'effective_date' => 'required|date',
            'reason' => 'required|string|min:3|max:1000',
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'area' => 'correction',
            'effective_date' => 'effective date',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'area.not_in' => 'The correction must not be zero — use a negative value to remove stock or a positive value to add it.',
            'reason.required' => 'Please record why this correction is being made.',
        ];
    }
}
