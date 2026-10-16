<?php

namespace App\Services\Notifications;

use App\Models\Client;
use App\Models\Notification;
use App\Models\SmsTemplate;
use App\Services\Sms\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Queues one SMS per client phone. Nothing here calls the gateway;
 * pending notifications are sent by the usual schedule.
 */
class ClientSmsBroadcast
{
    /** Filters on the clients list that narrow who "everyone matching" includes. */
    public const FILTERS = [
        'client_type',
        'author',
        'name',
        'email',
        'phone_number',
        'address',
    ];

    /**
     * True when a filtered send has no list filter and no table search,
     * so the audience is every client.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function everyone(array $filters, string $search): bool
    {
        foreach (self::FILTERS as $name) {
            $value = $filters[$name] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            return false;
        }

        return trim($search) === '';
    }

    /**
     * @return array{ok: bool, message: ?string, queued: int, skipped: int, duplicates: int, sample: ?string}
     */
    public function preview(Builder $clients, string $body): array
    {
        return $this->walk($clients, $body, false, null);
    }

    /**
     * @return array{ok: bool, message: ?string, queued: int, skipped: int, duplicates: int, sample: ?string}
     */
    public function send(Builder $clients, string $body, ?SmsTemplate $template = null): array
    {
        try {
            return DB::transaction(function () use ($clients, $body, $template) {
                return $this->walk($clients, $body, true, $template);
            });
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * @return array{ok: bool, message: ?string, queued: int, skipped: int, duplicates: int, sample: ?string}
     */
    protected function walk(Builder $clients, string $body, bool $queue, ?SmsTemplate $template): array
    {
        $body = trim($body);

        if ($body === '') {
            return $this->fail(__('client.sms.errors.empty'));
        }

        if (mb_strlen($body) > 1000) {
            return $this->fail(__('notification.errors.too_long'));
        }

        $unknown = array_values(array_diff(
            SmsTemplate::placeholders($body),
            ['client_name', 'client_phone']
        ));

        if ($unknown !== []) {
            return $this->fail(__('notification.errors.unresolved', [
                'variables' => implode(', ', array_map(fn (string $name) => '{'.$name.'}', $unknown)),
            ]));
        }

        $queued = 0;
        $skipped = 0;
        $duplicates = 0;
        $sample = null;
        $seen = [];

        $table = $clients->getModel()->getTable();
        $query = (clone $clients)->setEagerLoads([])->reorder()->select([
            "{$table}.id",
            "{$table}.name",
            "{$table}.phone_number",
        ]);

        $query->chunkById(200, function ($chunk) use ($body, $queue, $template, &$queued, &$skipped, &$duplicates, &$sample, &$seen) {
            foreach ($chunk as $client) {
                if (! $client instanceof Client) {
                    continue;
                }

                $phone = PhoneNumber::normalize($client->phone_number);

                if ($phone === null) {
                    $skipped++;

                    continue;
                }

                if (isset($seen[$phone])) {
                    $duplicates++;

                    continue;
                }

                $rendered = trim(SmsTemplate::interpolate($body, SmsTemplate::variablesFromClient($client)));

                if ($rendered === '' || SmsTemplate::placeholders($rendered) !== [] || mb_strlen($rendered) > 1000) {
                    $skipped++;

                    continue;
                }

                $seen[$phone] = true;
                $sample ??= $rendered;
                $queued++;

                if ($queue) {
                    Notification::queueCustomSms($phone, $rendered, $client, $template, false);
                }
            }
        }, "{$table}.id", 'id');

        if ($queued === 0) {
            return $this->fail(__('client.sms.errors.nobody'), $skipped, $duplicates);
        }

        return [
            'ok' => true,
            'message' => null,
            'queued' => $queued,
            'skipped' => $skipped,
            'duplicates' => $duplicates,
            'sample' => $sample,
        ];
    }

    /**
     * @return array{ok: bool, message: ?string, queued: int, skipped: int, duplicates: int, sample: ?string}
     */
    protected function fail(string $message, int $skipped = 0, int $duplicates = 0): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'queued' => 0,
            'skipped' => $skipped,
            'duplicates' => $duplicates,
            'sample' => null,
        ];
    }
}
