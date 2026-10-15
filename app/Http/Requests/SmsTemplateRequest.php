<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmsTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title', '')),
            'text' => trim((string) $this->input('text', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->input('id') ?: $this->route('id');

        return [
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sms_templates', 'title')->ignore($id),
            ],
            'text' => 'required|string|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('sms_template.title'),
            'text' => __('sms_template.text'),
        ];
    }
}
