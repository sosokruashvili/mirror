<?php

namespace Tests\Unit;

use App\Facades\Sms;
use App\Models\Client;
use App\Models\ClientAccount;
use App\Models\Notification;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Notifications\SmsNotificationDispatcher;
use App\Services\Sms\SmsException;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsResult;
use App\Services\Sms\SmsSender;
use App\Support\Cabinet\Cabinet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmsNotificationDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'sms_template_id')) {
            $this->fail('The notifications table does not exist yet.');
        }

        if (! Schema::hasTable('sms_templates')) {
            $this->fail('The sms_templates table does not exist yet.');
        }

        // Uncommitted rows stay invisible to the scheduler, so a test cannot
        // leave a real SMS waiting to be sent.
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_queueing_an_sms_does_not_send_it(): void
    {
        $fake = Sms::fake();

        $notification = Notification::queueSms('577123456', $this->smsTemplate('hello'));

        $this->assertSame(Notification::STATUS_PENDING, $notification->status);
        $this->assertSame('577123456', $notification->recipient);
        $this->assertSame([], $fake->sent);
    }

    public function test_cabinet_sms_is_georgian_and_points_at_a_token_url(): void
    {
        config(['cabinet.host' => 'client.mirrorgallery.ge']);
        app()->setLocale('en');

        $url = Cabinet::accessUrl('abc12345');
        $template = SmsTemplate::findByKey(SmsTemplate::KEY_CABINET_ACCESS);
        $template->text = 'თქვენი ელექტრონული კაბინეტის მისამართი: {cabinet_url}';
        $template->save();

        $this->assertSame('https://client.mirrorgallery.ge/abc12345', Cabinet::accessUrl('abc12345'));
        $this->assertSame(
            'თქვენი ელექტრონული კაბინეტის მისამართი: '.$url,
            (new Client())->cabinetSmsBody($url)
        );
        $this->assertStringNotContainsString('/login', $url);
    }

    public function test_dispatcher_sends_pending_sms_and_leaves_other_rows(): void
    {
        $fake = Sms::fake();

        $pending = Notification::queueSms('577123456', $this->smsTemplate('hello'));
        $sent = Notification::queueSms('577000000', $this->smsTemplate('already gone'));
        $sent->forceFill([
            'status' => Notification::STATUS_SENT,
            'sent_at' => now(),
        ])->save();

        $other = new Notification();
        $other->forceFill([
            'type' => 'email',
            'recipient' => 'person@example.com',
            'body' => 'not an sms',
            'status' => Notification::STATUS_PENDING,
        ])->save();

        $result = $this->dispatcher()->dispatch();

        $this->assertSame(['sent' => 1, 'failed' => 0, 'retried' => 0], $result);
        $this->assertSame(Notification::STATUS_SENT, $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->sent_at);
        $this->assertSame(1, $pending->fresh()->attempts);
        $this->assertSame(Notification::STATUS_SENT, $sent->fresh()->status);
        $this->assertSame(Notification::STATUS_PENDING, $other->fresh()->status);
        $this->assertTrue($fake->sentTo('577123456'));
        $this->assertSame('hello', $fake->sent[0]['message']->content);
        $this->assertSame('n'.$pending->id, $fake->sent[0]['message']->reference);
    }

    public function test_a_retryable_failure_stays_pending_until_the_attempt_limit(): void
    {
        $this->app->instance(SmsSender::class, new class implements SmsSender {
            public function send(string|iterable $to, string|SmsMessage $message): SmsResult
            {
                throw SmsException::fromGatewayCode(SmsException::TEMPORARY_DELAY);
            }

            public function balance(): ?int
            {
                return null;
            }

            public function status(string $destination, string $reference): ?string
            {
                return null;
            }
        });

        $notification = Notification::queueSms('577123456', $this->smsTemplate('hello'));

        $first = $this->dispatcher()->dispatch();

        $this->assertSame(1, $first['retried']);
        $this->assertSame(Notification::STATUS_PENDING, $notification->fresh()->status);
        $this->assertSame(1, $notification->fresh()->attempts);

        $notification->forceFill(['attempts' => Notification::MAX_ATTEMPTS - 1])->save();

        $last = $this->dispatcher()->dispatch();

        $this->assertSame(1, $last['failed']);
        $this->assertSame(Notification::STATUS_FAILED, $notification->fresh()->status);
        $this->assertSame(Notification::MAX_ATTEMPTS, $notification->fresh()->attempts);
    }

    public function test_an_invalid_number_is_failed_without_a_retry(): void
    {
        Sms::fake();

        $notification = new Notification();
        $notification->forceFill([
            'type' => Notification::TYPE_SMS,
            'recipient' => '123',
            'body' => 'hello',
            'status' => Notification::STATUS_PENDING,
        ])->save();

        $result = $this->dispatcher()->dispatch();

        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['retried']);
        $this->assertSame(Notification::STATUS_FAILED, $notification->fresh()->status);
    }

    public function test_a_stale_sending_row_is_sent_on_the_next_run(): void
    {
        $fake = Sms::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));

        $notification = Notification::queueSms('577123456', $this->smsTemplate('hello'));
        $notification->forceFill(['status' => Notification::STATUS_SENDING])->save();
        Notification::query()->whereKey($notification->id)->update([
            'updated_at' => now()->subMinutes(11),
        ]);

        $result = $this->dispatcher()->dispatch();

        $this->assertSame(1, $result['sent']);
        $this->assertSame(Notification::STATUS_SENT, $notification->fresh()->status);
        $this->assertTrue($fake->sentTo('577123456'));
    }

    public function test_scheduler_runs_the_sender_every_two_minutes(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $matches = fn ($event) => str_contains((string) $event->command, 'notifications:send');

        if (! in_array(base_path('routes/console.php'), get_included_files(), true)) {
            require base_path('routes/console.php');
        }

        $event = collect($schedule->events())->first($matches);

        $this->assertNotNull($event);
        $this->assertSame('*/2 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(10, $event->expiresAt);
    }

    public function test_client_rows_include_the_cabinet_action(): void
    {
        $user = $this->admin();

        $this->withoutMiddleware($this->csrfMiddleware());

        $rows = $this->actingAs($user, 'backpack')->postJson('/admin/client/search', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => ''],
        ]);

        $rows->assertOk();
        $html = json_encode($rows->json(), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('კაბინეტის დაშვება', $html);
        $this->assertStringContainsString('allow-cabinet', $html);
        $this->assertStringContainsString('allowCabinet', $html);
        $this->assertStringNotContainsString('/login', $html);
    }

    public function test_notification_list_renders(): void
    {
        $list = $this->actingAs($this->admin(), 'backpack')->get('/admin/notification');

        $list->assertOk();
        $this->assertStringContainsString('შეტყობინებები', $list->getContent());
    }

    public function test_notification_list_search_renders_rows(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $rows = $this->actingAs($this->admin(), 'backpack')->postJson('/admin/notification/search', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'კაბინეტი'],
        ]);

        $rows->assertOk();
        $rows->assertJsonStructure(['data', 'recordsTotal']);
    }

    public function test_notification_create_form_explains_the_queue(): void
    {
        $create = $this->actingAs($this->admin(), 'backpack')->get('/admin/notification/create');

        $create->assertOk();
        $html = $create->getContent();
        $this->assertStringContainsString('ყოველ 2 წუთში', $html);
        $this->assertStringContainsString('option value="cabinet"', $html);
        $this->assertStringContainsString('name="client_id"', $html);
        $this->assertStringContainsString('name="sms_template_id"', $html);
        $this->assertStringContainsString('აირჩიეთ კლიენტი', $html);
        $this->assertStringContainsString('აირჩიეთ შაბლონი', $html);
        $this->assertStringContainsString('notification-client-field', $html);
        $this->assertDoesNotMatchRegularExpression('/notification-client-field"\s+style="display:none"/', $html);
    }

    public function test_cabinet_notification_is_not_sent_by_the_walker(): void
    {
        $fake = Sms::fake();
        $client = new Client();
        $client->id = 15;

        $cabinet = Notification::queueCabinet('cabinet copy', $client);

        $this->assertSame(Notification::TYPE_CABINET, $cabinet->type);
        $this->assertSame(Notification::STATUS_SENT, $cabinet->status);
        $this->assertNotNull($cabinet->sent_at);
        $this->assertSame(Client::class, $cabinet->notifiable_type);
        $this->assertSame(15, (int) $cabinet->notifiable_id);
        $this->assertSame([], $fake->sent);

        $cabinet->forceFill([
            'status' => Notification::STATUS_PENDING,
            'sent_at' => null,
        ])->save();

        $queued = Notification::query()
            ->where('type', Notification::TYPE_SMS)
            ->where('status', Notification::STATUS_PENDING)
            ->pluck('id');

        $this->assertFalse($queued->contains($cabinet->id));
        $this->assertSame(Notification::STATUS_PENDING, $cabinet->fresh()->status);
    }

    public function test_cabinet_notification_is_shown_only_to_that_client(): void
    {
        $fake = Sms::fake();

        $account = ClientAccount::query()
            ->where('is_active', true)
            ->whereHas('client')
            ->with('client')
            ->first();

        if (! $account || ! $account->client) {
            $this->fail('An active cabinet account is required.');
        }

        $other = Client::query()->whereKeyNot($account->client_id)->orderByDesc('id')->first();

        if (! $other) {
            $this->fail('A second client is required.');
        }

        $mine = 'own-cabinet-marker-'.uniqid();
        $theirs = 'other-cabinet-marker-'.uniqid();
        $sms = 'sms-not-in-cabinet-'.uniqid();

        $account->client->queueCabinet($mine);
        $other->queueCabinet($theirs);
        $account->client->queueSms($this->smsTemplate($sms));

        $host = (string) config('cabinet.host');
        $session = ['cabinet_session_version' => (int) $account->session_version];

        $page = $this->actingAs($account, 'cabinet')
            ->withSession($session)
            ->get('https://'.$host.'/notifications');

        $page->assertOk();
        $page->assertSee($mine, false);
        $page->assertDontSee($theirs, false);
        $page->assertDontSee($sms, false);
        $page->assertSee('შეტყობინებები', false);

        $home = $this->actingAs($account, 'cabinet')
            ->withSession($session)
            ->get('https://'.$host.'/');

        $home->assertOk();
        $home->assertSee($mine, false);
        $home->assertDontSee($theirs, false);
        $home->assertDontSee($sms, false);
        $this->assertSame([], $fake->sent);
    }

    public function test_staff_can_save_a_cabinet_notification_for_a_client(): void
    {
        $fake = Sms::fake();
        $this->withoutMiddleware($this->csrfMiddleware());

        $client = Client::query()->orderByDesc('id')->first();

        if (! $client) {
            $this->fail('A client is required.');
        }

        $body = 'saved-cabinet-marker-'.uniqid();
        $template = SmsTemplate::query()->create([
            'title' => 'cabinet-'.uniqid(),
            'text' => 'Hello {client_name}',
        ]);

        $create = $this->actingAs($this->admin(), 'backpack')->post('/admin/notification', [
            'type' => Notification::TYPE_CABINET,
            'sms_template_id' => $template->id,
            'client_id' => $client->id,
            'body' => $body,
            '_save_action' => 'save_and_back',
        ]);

        $create->assertRedirect();

        $row = Notification::query()->where('body', $body)->first();

        $this->assertNotNull($row);
        $this->assertSame(Notification::TYPE_CABINET, $row->type);
        $this->assertSame(Notification::STATUS_SENT, $row->status);
        $this->assertNotNull($row->sent_at);
        $this->assertSame(Client::class, $row->notifiable_type);
        $this->assertSame($client->id, (int) $row->notifiable_id);
        $this->assertSame($template->id, (int) $row->sms_template_id);
        $this->assertSame([], $fake->sent);

        $visible = Notification::query()->visibleInCabinet($client)->where('body', $body)->exists();
        $this->assertTrue($visible);
    }

    public function test_cabinet_edit_form_selects_the_client_and_hides_the_phone(): void
    {
        $client = Client::query()->orderBy('id')->first();

        if (! $client) {
            $this->fail('A client is required.');
        }

        $notification = $client->queueCabinet('edit-cabinet-'.uniqid());

        $edit = $this->actingAs($this->admin(), 'backpack')
            ->get('/admin/notification/'.$notification->id.'/edit');

        $edit->assertOk();
        $html = $edit->getContent();
        $this->assertStringContainsString('value="'.$client->id.'" selected', $html);
        $this->assertMatchesRegularExpression('/notification-recipient-field"\s+style="display:none"/', $html);
        $this->assertDoesNotMatchRegularExpression('/notification-client-field"\s+style="display:none"/', $html);
        $this->assertStringContainsString('ეს ტექსტი მხოლოდ ამ კლიენტის კაბინეტში გამოჩნდება', $html);
    }

    public function test_cabinet_row_is_badged_in_the_notification_list(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $client = Client::query()->orderBy('id')->first();

        if (! $client) {
            $this->fail('A client is required.');
        }

        $body = 'badge-cabinet-'.uniqid();
        $client->queueCabinet($body);

        $rows = $this->actingAs($this->admin(), 'backpack')->postJson('/admin/notification/search', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => $body],
        ]);

        $rows->assertOk();
        $flat = json_encode($rows->json('data'), JSON_UNESCAPED_UNICODE);
        $this->assertIsString($flat);
        $this->assertStringContainsString($body, $flat);
        $this->assertStringContainsString('badge bg-primary', $flat);
        $this->assertStringContainsString('კაბინეტში', $flat);
        $this->assertStringContainsString($client->name, $flat);
    }

    public function test_allow_cabinet_creates_a_token_link_and_does_not_send_it(): void
    {
        $user = $this->admin();
        $client = Client::query()
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->orderByDesc('id')
            ->first();

        if (! $client) {
            $this->fail('A client with a phone is required.');
        }

        $template = SmsTemplate::findByKey(SmsTemplate::KEY_CABINET_ACCESS);
        $template->text = 'ბმული: {cabinet_url}';
        $template->save();

        $fake = Sms::fake();
        $this->withoutMiddleware($this->csrfMiddleware());

        $send = $this->actingAs($user, 'backpack')->postJson('/admin/client/'.$client->id.'/allow-cabinet');
        $send->assertOk();
        $send->assertJson(['success' => true]);

        $notification = Notification::query()->where('notifiable_id', $client->id)->latest('id')->first();
        $account = ClientAccount::query()->where('client_id', $client->id)->first();

        $this->assertNotNull($notification);
        $this->assertNotNull($account);
        $this->assertTrue($account->is_active);
        $this->assertSame(Notification::STATUS_PENDING, $notification->status);
        $this->assertSame(Notification::TYPE_SMS, $notification->type);
        $this->assertSame($template->id, (int) $notification->sms_template_id);
        $this->assertMatchesRegularExpression(
            '#https://'.preg_quote((string) config('cabinet.host'), '#').'/([a-f0-9]{8})#',
            $notification->body
        );
        preg_match('#/([a-f0-9]{8})$#', $notification->body, $match);
        $this->assertSame(Cabinet::hashAccessToken($match[1]), $account->access_token_hash);
        $this->assertSame([], $fake->sent);

        $host = (string) config('cabinet.host');
        $this->get('https://'.$host.'/deadbeef')->assertNotFound();
        $this->get('https://'.$host.'/'.$match[1])->assertRedirect();

        $again = $this->actingAs($user, 'backpack')->postJson('/admin/client/'.$client->id.'/allow-cabinet');
        $again->assertOk();
        $this->get('https://'.$host.'/'.$match[1])->assertNotFound();
    }

    public function test_template_placeholders_are_filled_from_the_client(): void
    {
        $client = new Client();
        $client->name = 'ნინო';
        $client->phone_number = '577123456';

        $text = SmsTemplate::interpolate('გამარჯობა {client_name}, ნომერი {client_phone}. {order_number}', SmsTemplate::variablesFromClient($client));

        $this->assertSame('გამარჯობა ნინო, ნომერი 577123456. {order_number}', $text);
        $this->assertSame(['order_number'], SmsTemplate::placeholders($text));
    }

    public function test_staff_can_save_an_sms_template(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $title = 'ready-'.uniqid();
        $create = $this->actingAs($this->admin(), 'backpack')->get('/admin/sms-template/create');
        $create->assertOk();
        $this->assertStringContainsString('{client_name}', $create->getContent());
        $this->assertStringContainsString('კლიენტის სახელი', $create->getContent());

        $save = $this->actingAs($this->admin(), 'backpack')->post('/admin/sms-template', [
            'title' => $title,
            'text' => 'გამარჯობა {client_name}',
            '_save_action' => 'save_and_back',
        ]);

        $save->assertSessionHasNoErrors();
        $save->assertRedirect();
        $this->assertNotNull(SmsTemplate::query()->where('title', $title)->first());

        $list = $this->actingAs($this->admin(), 'backpack')->get('/admin/sms-template');
        $list->assertOk();
        $this->assertStringContainsString('SMS შაბლონები', $list->getContent());
    }

    public function test_sms_notification_stores_the_template_and_fills_variables(): void
    {
        $fake = Sms::fake();
        $this->withoutMiddleware($this->csrfMiddleware());

        $client = Client::query()
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->orderByDesc('id')
            ->first();

        if (! $client || \App\Services\Sms\PhoneNumber::normalize($client->phone_number) === null) {
            $this->fail('A client with a usable phone is required.');
        }

        $template = SmsTemplate::query()->create([
            'title' => 'sms-'.uniqid(),
            'text' => 'გამარჯობა {client_name}',
        ]);

        $save = $this->actingAs($this->admin(), 'backpack')->post('/admin/notification', [
            'type' => Notification::TYPE_SMS,
            'sms_template_id' => $template->id,
            'client_id' => $client->id,
            'recipient' => '',
            'body' => '',
            '_save_action' => 'save_and_back',
        ]);

        $save->assertSessionHasNoErrors();
        $save->assertRedirect();

        $row = Notification::query()->where('sms_template_id', $template->id)->latest('id')->first();

        $this->assertNotNull($row);
        $this->assertSame('გამარჯობა '.$client->name, $row->body);
        $this->assertSame(Notification::STATUS_PENDING, $row->status);
        $this->assertSame(Notification::TYPE_SMS, $row->type);
        $this->assertSame($client->id, (int) $row->notifiable_id);
        $this->assertSame([], $fake->sent);
    }

    public function test_an_sms_notification_without_a_template_is_rejected(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $body = 'plain-sms-'.uniqid();

        $save = $this->actingAs($this->admin(), 'backpack')->post('/admin/notification', [
            'type' => Notification::TYPE_SMS,
            'recipient' => '577123456',
            'body' => $body,
            '_save_action' => 'save_and_back',
        ]);

        $save->assertSessionHasErrors(['sms_template_id']);
        $this->assertNull(Notification::query()->where('body', $body)->first());
    }

    public function test_queue_sms_renders_the_template_and_keeps_its_id(): void
    {
        $template = SmsTemplate::query()->create([
            'title' => 'queue-'.uniqid(),
            'text' => 'Hello {client_name}',
        ]);
        $client = new Client();
        $client->name = 'Nino';
        $client->phone_number = '577000111';

        $notification = Notification::queueSms('577123456', $template, $client);

        $this->assertSame('Hello Nino', $notification->body);
        $this->assertSame($template->id, (int) $notification->sms_template_id);
        $this->assertSame(Notification::STATUS_PENDING, $notification->status);
    }

    protected function smsTemplate(string $text): SmsTemplate
    {
        return SmsTemplate::query()->create([
            'title' => 'test-'.uniqid(),
            'text' => $text,
        ]);
    }

    protected function admin(): User
    {
        $user = User::query()->whereHas('roles', function ($query) {
            $query->where('slug', 'admin');
        })->first();

        if (! $user) {
            $this->fail('An admin user is required.');
        }

        return $user;
    }

    /**
     * @return list<class-string>
     */
    protected function csrfMiddleware(): array
    {
        return [
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ];
    }

    protected function dispatcher(): SmsNotificationDispatcher
    {
        return $this->app->make(SmsNotificationDispatcher::class);
    }
}
