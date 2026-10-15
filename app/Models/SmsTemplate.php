<?php

namespace App\Models;

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

    /** @var list<string> */
    public const VARIABLES = [
        'client_name',
        'client_phone',
        'cabinet_url',
    ];

    protected $table = 'sms_templates';

    protected $fillable = [
        'title',
        'text',
    ];

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
            return self::variablesFromClient($notifiable->client);
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
