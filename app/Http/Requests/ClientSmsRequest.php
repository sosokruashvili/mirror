<?php

namespace App\Http\Requests;

use App\Services\Notifications\ClientSmsBroadcast;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = backpack_user();

        return $user
            && $user->can('client.list')
            && $user->can('notification.create');
    }

    protected function prepareForValidation(): void
    {
        $templateId = $this->input('sms_template_id');
        $entries = collect((array) $this->input('entries', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'scope' => trim((string) $this->input('scope', '')),
            'body' => trim((string) $this->input('body', '')),
            'search' => trim((string) $this->input('search', '')),
            'entries' => $entries,
            'sms_template_id' => ($templateId === null || $templateId === '') ? null : (int) $templateId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', Rule::in(['selected', 'filtered'])],
            'entries' => [
                Rule::requiredIf(fn () => $this->input('scope') === 'selected'),
                'array',
                'max:2000',
            ],
            'entries.*' => ['integer'],
            'body' => ['required', 'string', 'max:1000'],
            'search' => ['nullable', 'string', 'max:255'],
            'sms_template_id' => ['nullable', 'integer', Rule::exists('sms_templates', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scope.required' => __('client.sms.errors.scope'),
            'scope.in' => __('client.sms.errors.scope'),
            'entries.required' => __('client.sms.errors.none_selected'),
            'entries.max' => __('client.sms.errors.too_many'),
            'body.required' => __('client.sms.errors.empty'),
            'body.max' => __('notification.errors.too_long'),
        ];
    }

    public function audienceIsEveryone(): bool
    {
        if ($this->input('scope') !== 'filtered') {
            return false;
        }

        return ClientSmsBroadcast::everyone(
            $this->only(ClientSmsBroadcast::FILTERS),
            (string) $this->input('search', '')
        );
    }
}
