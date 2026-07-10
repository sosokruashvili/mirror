<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->get('id');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stages', 'name')->ignore($id),
            ],
            'title' => 'required|string|max:255',
            'color' => 'required|string|max:9',
            'position' => 'nullable|integer|min:0',
        ];
    }
}
