<?php

namespace App\Models;

use App\Support\Cabinet\Cabinet;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable SMS / cabinet message. `text` may contain placeholders
 * such as {client_name}, which are filled when a notification is saved.
 */
class SmsTemplate extends Model
{
    use CrudTrait;

    /** SMS with the client's private cabinet link. */
    public const KEY_CABINET_ACCESS = 'cabinet_access';

    /** SMS sent when an order's production is done (status ready). */
    public const KEY_ORDER_READY = 'order_ready';

    /** Keys the application looks up. These templates stay locked in the admin. */
    public const SYSTEM_KEYS = [
        self::KEY_CABINET_ACCESS,
        self::KEY_ORDER_READY,
    ];

    /** @var list<string> */
    public const VARIABLES = [
        'client_name',
        'client_phone',
        'cabinet_url',
        'order_id',
    ];

    /** Sample order number used only by a template test SMS. */
    public const TEST_ORDER_ID = '1234';

    protected $table = 'sms_templates';

    protected $fillable = [
        'title',
        'key',
        'text',
    ];

    public function isSystem(): bool
    {
        return in_array($this->key, self::SYSTEM_KEYS, true);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * The template a piece of the system sends. Missing means that SMS cannot go out.
     */
    public static function findByKey(string $key): self
    {
        $template = static::query()->where('key', $key)->first();

        if (! $template) {
            throw new \InvalidArgumentException(__('sms_template.errors.missing', ['key' => $key]));
        }

        return $template;
    }

    /**
     * Sample cabinet link used only by a template test SMS.
     */
    public static function testCabinetUrl(): string
    {
        return 'https://'.trim((string) config('cabinet.host')).'/test';
    }

    /**
     * Values the template form's SMS counter puts in place of each placeholder,
     * so it counts the text as it is sent. Only their length matters. The name
     * is Georgian (most client names are) and as long as 90% of client names.
     *
     * @return array<string, string>
     */
    public static function counterSamples(): array
    {
        $names = Client::query()
            ->whereNotNull('name')
            ->pluck('name')
            ->map(fn ($name) => mb_strlen(trim((string) $name)))
            ->sort()
            ->values();
        $nameLength = $names->isEmpty() ? 15 : $names[(int) floor(0.9 * ($names->count() - 1))];

        return [
            'client_name' => str_repeat('ა', max(1, $nameLength)),
            'client_phone' => '+995555000000',
            'cabinet_url' => Cabinet::accessUrl(str_repeat('0', 8)),
            'order_id' => (string) max(1, (int) Order::query()->max('id')),
        ];
    }

    /**
     * Template text as a test SMS would send it. Name, cabinet link and order
     * number are samples; the phone is the number the test is addressed to.
     */
    public function renderForTest(string $phone): string
    {
        return $this->render([
            'client_name' => __('sms_template.test.sample_name'),
            'client_phone' => trim($phone),
            'cabinet_url' => self::testCabinetUrl(),
            'order_id' => self::TEST_ORDER_ID,
        ]);
    }

    /**
     * Template text with placeholders filled. Caller values win over ones taken from the record.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public function render(array $variables = [], ?Model $notifiable = null): string
    {
        return self::interpolate($this->text, array_merge(self::variablesFrom($notifiable), $variables));
    }

    /**
     * Values the known placeholders can take from this record.
     *
     * @return array<string, string>
     */
    public static function variablesFrom(?Model $notifiable): array
    {
        if ($notifiable instanceof Client) {
            return self::variablesFromClient($notifiable);
        }

        if ($notifiable instanceof Order) {
            return array_merge(self::variablesFromClient($notifiable->client), [
                'order_id' => (string) $notifiable->getKey(),
            ]);
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function variablesFromClient(?Client $client): array
    {
        if (! $client) {
            return [];
        }

        return [
            'client_name' => trim((string) $client->name),
            'client_phone' => trim((string) $client->phone_number),
        ];
    }

    /**
     * Replace {placeholders} that have a value. Unknown or empty ones stay,
     * so the caller can refuse to save a message that still contains them.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public static function interpolate(string $text, array $variables): string
    {
        foreach ($variables as $name => $value) {
            if (! is_string($name) || ! in_array($name, self::VARIABLES, true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * Placeholder names still written as {name} in the text.
     *
     * @return list<string>
     */
    public static function placeholders(string $text): array
    {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * One line for the template form, e.g. "{client_name} (client name)".
     */
    public static function variableHint(): string
    {
        $parts = [];

        foreach (self::VARIABLES as $name) {
            $parts[] = '{'.$name.'} ('.__('sms_template.variables.'.$name).')';
        }

        return implode(', ', $parts);
    }
}
