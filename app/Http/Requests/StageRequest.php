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
     * Trim the machine name before validating so a stray leading/trailing
     * space doesn't produce a confusing "invalid characters" error.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
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
                // Machine identifier used in lookups and as an index — keep it
                // to latin letters, digits, underscores and dashes (no spaces).
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('stages', 'name')->ignore($id),
            ],
            'title' => 'required|string|max:255',
            'color' => 'required|string|max:9',
            'position' => 'nullable|integer|min:0',
            'is_universal' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'The name may only contain latin letters, numbers, underscores and dashes — no spaces (e.g. frame_assembly).',
        ];
    }
}
