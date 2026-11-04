<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'stage' => 'required|integer|exists:stages,id',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:100',
            'price' => 'nullable|numeric|min:0',
            'price_gel' => 'nullable|numeric|min:0',
            'cutloss' => 'nullable|integer|min:0',
            'companion_service_ids' => 'nullable|array',
            'companion_service_ids.*' => [
                'integer',
                'exists:services,id',
                Rule::notIn(array_filter([$this->route('id')])),
            ],
        ];
    }
}
