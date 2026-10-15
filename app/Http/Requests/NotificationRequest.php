<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Notification;
use App\Models\SmsTemplate;
use App\Services\Sms\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $type = trim((string) $this->input('type', Notification::TYPE_SMS));
        $recipient = trim((string) $this->input('recipient', ''));
        $clientId = $this->input('client_id');
        $clientId = ($clientId === null || $clientId === '') ? null : (int) $clientId;
        $templateId = $this->input('sms_template_id');
        $templateId = ($templateId === null || $templateId === '') ? null : (int) $templateId;
        $client = $clientId ? Client::query()->find($clientId) : null;

        if ($type === Notification::TYPE_CABINET && $client) {
            $recipient = Notification::cabinetRecipient($client);
        } elseif ($type === Notification::TYPE_SMS && $recipient === '' && $client?->phone_number) {
            $recipient = trim((string) $client->phone_number);
        }

        $body = trim((string) $this->input('body', ''));
        $template = $templateId ? SmsTemplate::query()->find($templateId) : null;

        if ($body === '' && $template) {
            $body = trim($template->text);
        }

        $body = SmsTemplate::interpolate($body, SmsTemplate::variablesFromClient($client));

        $this->merge([
            'type' => $type,
            'recipient' => $recipient,
            'body' => $body,
            'client_id' => $clientId,
            'sms_template_id' => $templateId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(Notification::typeOptions()))],
            'sms_template_id' => ['required', 'integer', Rule::exists('sms_templates', 'id')],
            'recipient' => [
                Rule::requiredIf(fn () => $this->input('type') !== Notification::TYPE_CABINET),
                'nullable',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->input('type') === Notification::TYPE_SMS && PhoneNumber::normalize((string) $value) === null) {
                        $fail(__('notification.errors.phone'));
                    }
                },
            ],
            'client_id' => [
                Rule::requiredIf(fn () => $this->input('type') === Notification::TYPE_CABINET),
                'nullable',
                'integer',
                Rule::exists('clients', 'id'),
            ],
            'body' => [
                'required',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $left = SmsTemplate::placeholders((string) $value);

                    if ($left === []) {
                        return;
                    }

                    $fail(__('notification.errors.unresolved', [
                        'variables' => implode(', ', array_map(fn (string $name) => '{'.$name.'}', $left)),
                    ]));
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('notification.type'),
            'sms_template_id' => __('notification.template'),
            'recipient' => __('notification.recipient'),
            'client_id' => __('notification.client'),
            'body' => __('notification.body'),
        ];
    }
}
