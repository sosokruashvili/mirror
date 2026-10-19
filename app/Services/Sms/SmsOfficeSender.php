<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * smsoffice.ge HTTP API v2 driver.
 *
 * @see https://smsoffice.ge/integration/
 */
class SmsOfficeSender implements SmsSender
{
    /**
     * @param array{key?: ?string, sender?: ?string, urgent?: bool, base_url?: string, timeout?: int} $config
     */
    public function __construct(
        protected HttpFactory $http,
        protected array $config,
    ) {
    }

    public function send(string|iterable $to, string|SmsMessage $message): SmsResult
    {
        $message = SmsMessage::from($message);
        $destinations = PhoneNumber::normalizeMany($to);

        if ($destinations === []) {
            throw SmsException::invalidDestination(PhoneNumber::describe($to));
        }

        $content = trim($message->content);

        if ($content === '') {
            throw new SmsException('SMS content is empty.');
        }

        if (mb_strlen($content) > 1000) {
            throw new SmsException('SMS content exceeds the 1000 character limit.');
        }

        $sender = $message->sender ?? $this->config['sender'] ?? null;

        if (! $sender) {
            throw SmsException::notConfigured('SMS_OFFICE_SENDER is empty');
        }

        $urgent = $message->urgent ?? (bool) ($this->config['urgent'] ?? true);

        $params = [
            'key' => $this->key(),
            'destination' => implode(',', $destinations),
            'sender' => $sender,
            'content' => $content,
            'urgent' => $urgent ? 'true' : 'false',
        ];

        if ($message->reference !== null && $message->reference !== '') {
            $params['reference'] = mb_substr($message->reference, 0, 20);
        }

        if ($message->scheduledAt) {
            $params['scheduledAt'] = $message->scheduledAt->getTimestamp();
        }

        // POST keeps long messages / many recipients clear of the 4 KB GET limit.
        $data = $this->request('post', '/api/v2/send/', $params);

        Log::info('SMS sent via smsoffice.ge', [
            'destinations' => $destinations,
            'sender' => $sender,
            'reference' => $params['reference'] ?? null,
            'gateway_message' => $data['Message'] ?? null,
        ]);

        return new SmsResult(
            destinations: $destinations,
            content: $content,
            reference: $params['reference'] ?? null,
            gatewayMessage: (string) ($data['Message'] ?? ''),
            output: is_array($data['Output'] ?? null) ? $data['Output'] : null,
        );
    }

    public function balance(): ?int
    {
        try {
            $response = $this->client()->get($this->url('/api/getBalance'), ['key' => $this->key()]);
        } catch (Throwable $e) {
            throw SmsException::transport($e);
        }

        $body = trim($response->body());

        if ($response->failed()) {
            throw new SmsException("smsoffice.ge balance request failed with HTTP {$response->status()}.");
        }

        // The endpoint answers with a bare number; be lenient and also accept
        // the JSON envelope in case they unify the API later.
        if (is_numeric($body)) {
            return (int) $body;
        }

        $json = json_decode($body, true);

        if (is_array($json)) {
            if (isset($json['ErrorCode']) && (int) $json['ErrorCode'] !== 0) {
                throw SmsException::fromGatewayCode((int) $json['ErrorCode'], (string) ($json['Message'] ?? ''), $json);
            }

            $value = $json['Output']['Balance'] ?? $json['Output'] ?? $json['Balance'] ?? null;

            return is_numeric($value) ? (int) $value : null;
        }

        throw new SmsException('smsoffice.ge returned an unexpected balance response: '.mb_substr($body, 0, 200));
    }

    public function status(string $destination, string $reference): ?string
    {
        $number = PhoneNumber::normalize($destination);

        if ($number === null) {
            throw SmsException::invalidDestination($destination);
        }

        $data = $this->request('get', '/api/v2/getMessageStatus/', [
            'key' => $this->key(),
            'destination' => $number,
            'reference' => $reference,
        ]);

        $status = $data['Output']['Status'] ?? null;

        return is_string($status) && $status !== '' ? $status : null;
    }

    /**
     * Perform a JSON API call and return the decoded envelope.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $params): array
    {
        try {
            /** @var Response $response */
            $response = $method === 'post'
                ? $this->client()->asForm()->post($this->url($path), $params)
                : $this->client()->get($this->url($path), $params);
        } catch (Throwable $e) {
            throw SmsException::transport($e);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new SmsException(
                "smsoffice.ge returned a non-JSON response (HTTP {$response->status()}): ".mb_substr($response->body(), 0, 200)
            );
        }

        $code = (int) ($data['ErrorCode'] ?? ($data['Success'] ?? false ? 0 : -1));

        if ($code !== 0 || empty($data['Success'])) {
            throw SmsException::fromGatewayCode($code, (string) ($data['Message'] ?? ''), $data);
        }

        return $data;
    }

    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->http
            ->timeout((int) ($this->config['timeout'] ?? 10))
            ->connectTimeout(5)
            ->acceptJson();
    }

    protected function url(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://smsoffice.ge'), '/').$path;
    }

    protected function key(): string
    {
        $key = trim((string) ($this->config['key'] ?? ''));

        if ($key === '') {
            throw SmsException::notConfigured('SMS_OFFICE_KEY is empty');
        }

        return $key;
    }
}
