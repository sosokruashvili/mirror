<?php

namespace App\Models;

use App\Services\Sms\PhoneNumber;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * An outbound message waiting to be delivered, or the record of one that was.
 *
 * Nothing in here talks to the SMS gateway. Callers only insert a row.
 * {@see \App\Services\Notifications\SmsNotificationDispatcher} sends rows
 * whose type is sms. Cabinet rows are shown in that client's cabinet and
 * are never handed to the gateway.
 */
class Notification extends Model
{
    use CrudTrait;

    public const TYPE_SMS = 'sms';

    public const TYPE_CABINET = 'cabinet';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** How many gateway attempts before a retryable failure is left as failed. */
    public const MAX_ATTEMPTS = 5;

    protected $table = 'notifications';

    protected $fillable = [
        'type',
        'sms_template_id',
        'recipient',
        'body',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Notification $notification) {
            $notification->attempts = 0;
            $notification->last_error = null;

            if ($notification->type === self::TYPE_CABINET) {
                // Visible in the cabinet as soon as the row exists.
                $notification->status = self::STATUS_SENT;
                $notification->sent_at ??= now();
            } else {
                // A crafted form post cannot mark an SMS sent and skip the walker.
                $notification->status = self::STATUS_PENDING;
                $notification->sent_at = null;
            }

            if ($notification->author === null && function_exists('backpack_user') && backpack_user()) {
                $notification->author = backpack_user()->id;
            }
        });
    }

    /**
     * Queue a message. An SMS is rendered from $template and is not sent here.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public static function queue(string $type, string $recipient, string $body, ?Model $notifiable = null, ?SmsTemplate $template = null, array $variables = []): self
    {
        $recipient = trim($recipient);
        $body = trim($body);

        if (! array_key_exists($type, self::typeOptions())) {
            throw new InvalidArgumentException(__('notification.errors.type'));
        }

        if ($type === self::TYPE_CABINET && ! $notifiable instanceof Client) {
            throw new InvalidArgumentException(__('notification.errors.client'));
        }

        if ($type === self::TYPE_SMS) {
            if (! $template?->getKey()) {
                throw new InvalidArgumentException(__('notification.errors.template'));
            }

            $body = trim($template->render($variables, $notifiable));
            $left = SmsTemplate::placeholders($body);

            if ($left !== []) {
                throw new InvalidArgumentException(__('notification.errors.unresolved', [
                    'variables' => implode(', ', array_map(fn (string $name) => '{'.$name.'}', $left)),
                ]));
            }
        }

        if ($type === self::TYPE_SMS && PhoneNumber::normalize($recipient) === null) {
            throw new InvalidArgumentException(__('notification.errors.phone'));
        }

        if ($recipient === '') {
            throw new InvalidArgumentException(__('notification.errors.recipient'));
        }

        if ($body === '') {
            throw new InvalidArgumentException(__('notification.errors.body'));
        }

        if (mb_strlen($body) > 1000) {
            throw new InvalidArgumentException(__('notification.errors.too_long'));
        }

        $notification = new static();
        $notification->forceFill([
            'type' => $type,
            'sms_template_id' => $template?->getKey(),
            'recipient' => $recipient,
            'body' => $body,
            'notifiable_type' => $notifiable?->getMorphClass(),
            'notifiable_id' => $notifiable?->getKey(),
        ])->save();

        return $notification;
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public static function queueSms(string $recipient, SmsTemplate $template, ?Model $notifiable = null, array $variables = []): self
    {
        return self::queue(self::TYPE_SMS, $recipient, '', $notifiable, $template, $variables);
    }

    /**
     * Publish a message in this client's cabinet. Does not send an SMS.
     */
    public static function queueCabinet(string $body, Client $client): self
    {
        return self::queue(self::TYPE_CABINET, self::cabinetRecipient($client), $body, $client);
    }

    /**
     * Short label stored in the recipient column. The cabinet itself looks
     * up the client through the notifiable relation, not this string.
     */
    public static function cabinetRecipient(Client $client): string
    {
        $phone = PhoneNumber::normalize($client->phone_number);

        if (is_string($phone) && $phone !== '') {
            return $phone;
        }

        return (string) ($client->getKey() ?? '');
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_SMS => __('notification.types.sms'),
            self::TYPE_CABINET => __('notification.types.cabinet'),
        ];
    }

    /**
     * Messages this client can read in the cabinet.
     */
    public function scopeVisibleInCabinet(Builder $query, Client $client): Builder
    {
        return $query
            ->where('type', self::TYPE_CABINET)
            ->where('notifiable_type', $client->getMorphClass())
            ->where('notifiable_id', $client->getKey());
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function smsTemplate()
    {
        return $this->belongsTo(SmsTemplate::class);
    }

    /**
     * Named authorUser() on purpose: a relationship that shares its name with
     * the author column makes Eloquent return the raw id.
     */
    public function authorUser()
    {
        return $this->belongsTo(User::class, 'author');
    }

    public function isEditable(): bool
    {
        if ($this->type === self::TYPE_CABINET) {
            return true;
        }

        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_FAILED], true);
    }

    /**
     * Put a pending or failed SMS back at the start of the queue.
     */
    public function releaseForSending(): void
    {
        if ($this->type !== self::TYPE_SMS) {
            return;
        }

        $this->forceFill([
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'last_error' => null,
            'sent_at' => null,
        ])->save();
    }
}
