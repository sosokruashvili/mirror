<?php

namespace App\Http\Requests;

use App\Models\SmsTemplate;
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
        $merge = [
            'title' => trim((string) $this->input('title', '')),
            'text' => trim((string) $this->input('text', '')),
        ];

        if ($this->exists('key')) {
            $key = strtolower(trim((string) $this->input('key')));
            $merge['key'] = $key === '' ? null : $key;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->input('id') ?: $this->route('id');
        $template = $id ? SmsTemplate::query()->find($id) : null;

        $key = $template?->isSystem()
            ? ['nullable', Rule::in([$template->key])]
            : [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('sms_templates', 'key')->ignore($id),
            ];

        return [
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sms_templates', 'title')->ignore($id),
            ],
            'key' => $key,
            'text' => 'required|string|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => __('sms_template.errors.key_format'),
            'key.in' => __('sms_template.errors.key_locked'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('sms_template.title'),
            'key' => __('sms_template.key'),
            'text' => __('sms_template.text'),
        ];
    }
}
