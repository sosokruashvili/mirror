<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Setting;
use App\Models\SmsTemplate;
use App\Models\Stage;
use App\Models\User;
use App\Services\Notifications\OrderReadySms;
use App\Services\OrderPieceStatusSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderReadySmsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'sms_template_id')) {
            $this->fail('The notifications table does not exist yet.');
        }

        // Uncommitted rows stay invisible to the scheduler, so a test cannot
        // leave a real SMS waiting to be sent.
        // Settings are cached. On a shared store the switch turned on below
        // would outlive the rollback and switch the real SMS on.
        if (config('cache.default') !== 'array') {
            $this->fail('These tests need CACHE_STORE=array.');
        }

        DB::beginTransaction();

        Setting::put(OrderReadySms::SETTING, '1');

        $template = SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_READY);
        $template->text = 'შეკვეთა #{order_id} მზადაა, {client_name}';
        $template->save();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_finishing_production_queues_the_order_ready_sms_once(): void
    {
        $phone = $this->localPhone();
        $client = $this->client($phone);
        $order = $this->serviceOrder($client);

        $order->setStageCompleted($this->stage(OrderPieceStatusSync::COMPLETION_STAGE), true);

        $this->assertSame('ready', $order->fresh()->status);

        $rows = $this->smsFor($order)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(Notification::STATUS_PENDING, $rows[0]->status);
        $this->assertSame($phone, $rows[0]->recipient);
        $this->assertSame('შეკვეთა #'.$order->id.' მზადაა, '.$client->name, $rows[0]->body);
        $this->assertSame(SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_READY)->id, (int) $rows[0]->sms_template_id);

        // A stage unticked and ticked again must not message the client twice.
        $order->update(['status' => 'working']);
        $order->update(['status' => 'ready']);

        $this->assertSame(1, $this->smsFor($order)->count());
    }

    public function test_nothing_is_queued_while_the_switch_is_off(): void
    {
        Setting::put(OrderReadySms::SETTING, '0');
        $order = $this->serviceOrder($this->client($this->localPhone()));

        $order->setStageCompleted($this->stage(OrderPieceStatusSync::COMPLETION_STAGE), true);

        $this->assertSame('ready', $order->fresh()->status);
        $this->assertSame(0, $this->smsFor($order)->count());
    }

    public function test_handing_out_drops_an_unsent_order_ready_sms(): void
    {
        $waiting = $this->serviceOrder($this->client($this->localPhone()));
        $waiting->update(['status' => 'ready']);

        $sent = $this->serviceOrder($this->client($this->localPhone()));
        $sent->update(['status' => 'ready']);
        $this->smsFor($sent)->update(['status' => Notification::STATUS_SENT, 'sent_at' => now()]);

        $waiting->update(['status' => 'finished']);
        $sent->update(['status' => 'finished']);

        $this->assertSame(0, $this->smsFor($waiting)->count());
        $this->assertSame(Notification::STATUS_SENT, $this->smsFor($sent)->value('status'));
    }

    public function test_an_order_without_a_usable_phone_still_becomes_ready(): void
    {
        $order = $this->serviceOrder($this->client('123'));

        $order->setStageCompleted($this->stage(OrderPieceStatusSync::COMPLETION_STAGE), true);

        $this->assertSame('ready', $order->fresh()->status);
        $this->assertSame(0, $this->smsFor($order)->count());
    }

    public function test_the_order_ready_template_is_locked_and_tests_with_a_sample_order(): void
    {
        $template = SmsTemplate::findByKey(SmsTemplate::KEY_ORDER_READY);

        $this->assertTrue($template->isSystem());
        $this->assertSame(
            'შეკვეთა #'.SmsTemplate::TEST_ORDER_ID.' მზადაა, '.__('sms_template.test.sample_name'),
            $template->renderForTest('577123456')
        );
    }

    protected function serviceOrder(Client $client): Order
    {
        return Order::query()->create([
            'status' => 'working',
            'order_type' => 'retail',
            'client_id' => $client->id,
            'currency_rate' => 1,
            'product_type' => 'service',
            'author' => $this->admin()->id,
        ]);
    }

    protected function client(string $phone): Client
    {
        return Client::query()->create([
            'name' => 'ready-'.uniqid(),
            'phone_number' => $phone,
            'address' => 'sms test',
            'client_type' => 0,
            'personal_id' => 'p'.uniqid(),
        ]);
    }

    protected function smsFor(Order $order)
    {
        return Notification::query()
            ->where('type', Notification::TYPE_SMS)
            ->where('notifiable_type', $order->getMorphClass())
            ->where('notifiable_id', $order->id);
    }

    protected function stage(string $name): Stage
    {
        $stage = Stage::query()->where('name', $name)->first();

        if (! $stage) {
            $this->fail('The '.$name.' stage is required.');
        }

        return $stage;
    }

    protected function localPhone(): string
    {
        return '577'.random_int(100000, 999999);
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
}
