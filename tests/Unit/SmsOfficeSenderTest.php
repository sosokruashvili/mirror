<?php

namespace Tests\Unit;

use App\Facades\Sms;
use App\Notifications\Channels\SmsChannel;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsException;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsOfficeSender;
use App\Services\Sms\SmsSender;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsOfficeSenderTest extends TestCase
{
    private function sender(array $overrides = []): SmsOfficeSender
    {
        return new SmsOfficeSender($this->app->make(HttpFactory::class), array_merge([
            'key' => 'secret-key',
            'sender' => 'MirrorGal',
            'urgent' => true,
            'base_url' => 'https://smsoffice.ge',
            'timeout' => 5,
        ], $overrides));
    }

    public function test_phone_numbers_are_normalised_to_international_digits(): void
    {
        $this->assertSame('995577123456', PhoneNumber::normalize('+995 577 12 34 56'));
        $this->assertSame('995577123456', PhoneNumber::normalize('00995577123456'));
        $this->assertSame('995577123456', PhoneNumber::normalize('577123456'));
        $this->assertSame('995577123456', PhoneNumber::normalize('577-12-34-56'));
        $this->assertNull(PhoneNumber::normalize('12345'));
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize(null));

        $this->assertSame(
            ['995577123456', '995599000000'],
            PhoneNumber::normalizeMany('577123456, +995599000000, bogus, 577123456')
        );
    }

    public function test_send_posts_a_form_request_to_smsoffice(): void
    {
        Http::fake([
            'smsoffice.ge/api/v2/send/' => Http::response([
                'Success' => true,
                'Message' => 'Accepted',
                'Output' => ['MessageId' => 1],
                'ErrorCode' => 0,
            ]),
        ]);

        $result = $this->sender()->send('577 123 456', SmsMessage::create('გამარჯობა')->reference('order-42'));

        $this->assertSame(['995557541332'], $result->destinations);
        $this->assertSame('order-42', $result->reference);
        $this->assertSame('Accepted', $result->gatewayMessage);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://smsoffice.ge/api/v2/send/'
                && $request->isForm()
                && $request['key'] === 'secret-key'
                && $request['destination'] === '995557541332'
                && $request['sender'] === 'MirrorGal'
                && $request['content'] === 'გამარჯობა'
                && $request['urgent'] === 'true'
                && $request['reference'] === 'order-42';
        });
    }

    public function test_message_options_override_configuration(): void
    {
        Http::fake(['smsoffice.ge/*' => Http::response(['Success' => true, 'Message' => '', 'Output' => null, 'ErrorCode' => 0])]);

        $this->sender()->send(['577123456', '599000000'], SmsMessage::create('Hi')->sender('Other')->urgent(false));

        Http::assertSent(function (Request $request) {
            return $request['destination'] === '995557541332'
                && $request['sender'] === 'Other'
                && $request['urgent'] === 'false'
                && ! isset($request['reference']);
        });
    }

    public function test_gateway_error_codes_become_exceptions(): void
    {
        Http::fake(['smsoffice.ge/*' => Http::response([
            'Success' => false,
            'Message' => 'Balance is insufficient',
            'Output' => null,
            'ErrorCode' => 20,
        ])]);

        try {
            $this->sender()->send('577123456', 'Hi');
            $this->fail('Expected SmsException');
        } catch (SmsException $e) {
            $this->assertSame(20, $e->getCode());
            $this->assertStringContainsString('Balance is insufficient', $e->getMessage());
            $this->assertFalse($e->isRetryable());
        }
    }

    public function test_temporary_delay_is_retryable(): void
    {
        Http::fake(['smsoffice.ge/*' => Http::response(['Success' => false, 'Message' => '', 'Output' => null, 'ErrorCode' => -100])]);

        try {
            $this->sender()->send('577123456', 'Hi');
            $this->fail('Expected SmsException');
        } catch (SmsException $e) {
            $this->assertTrue($e->isRetryable());
        }
    }

    public function test_invalid_destinations_fail_before_hitting_the_gateway(): void
    {
        Http::fake();

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Invalid SMS destination');

        try {
            $this->sender()->send('not a number', 'Hi');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_missing_api_key_is_reported(): void
    {
        Http::fake();

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('SMS_OFFICE_KEY');

        $this->sender(['key' => ''])->send('577123456', 'Hi');
    }

    public function test_balance_reads_the_bare_number_response(): void
    {
        Http::fake(['smsoffice.ge/api/getBalance*' => Http::response('1234')]);

        $this->assertSame(1234, $this->sender()->balance());

        Http::assertSent(fn (Request $request) => $request['key'] === 'secret-key');
    }

    public function test_status_returns_the_gateway_status(): void
    {
        Http::fake(['smsoffice.ge/api/v2/getMessageStatus/*' => Http::response([
            'Success' => true,
            'Message' => '',
            'Output' => ['Status' => 'Delivered'],
            'ErrorCode' => 0,
        ])]);

        $this->assertSame('Delivered', $this->sender()->status('+995577123456', 'order-42'));
    }

    public function test_container_resolves_the_configured_driver(): void
    {
        config(['sms.driver' => 'smsoffice']);
        $this->app->forgetInstance(SmsSender::class);

        $this->assertInstanceOf(SmsOfficeSender::class, $this->app->make(SmsSender::class));
    }

    public function test_notification_channel_routes_through_the_sms_sender(): void
    {
        $fake = Sms::fake();

        $notifiable = new class {
            use \Illuminate\Notifications\RoutesNotifications;

            public function routeNotificationForSms(): string
            {
                return '577123456';
            }
        };

        $notification = new class extends Notification {
            public function via(object $notifiable): array
            {
                return [SmsChannel::class];
            }

            public function toSms(object $notifiable): SmsMessage
            {
                return SmsMessage::create('Your order is ready')->reference('order-7');
            }
        };

        (new SmsChannel($fake))->send($notifiable, $notification);

        $this->assertCount(1, $fake->sent);
        $this->assertTrue($fake->sentTo('+995577123456'));
        $this->assertSame('Your order is ready', $fake->sent[0]['message']->content);
        $this->assertSame('order-7', $fake->sent[0]['message']->reference);
    }
}
