<?php

namespace Tests\Unit;

use App\Facades\Sms;
use App\Models\Client;
use App\Models\Notification;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Notifications\ClientSmsBroadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientSmsBroadcastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'sms_template_id')) {
            $this->fail('The notifications table does not exist yet.');
        }

        if (! Schema::hasTable('clients')) {
            $this->fail('The clients table does not exist yet.');
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_selected_clients_are_queued_once_per_phone(): void
    {
        $fake = Sms::fake();
        $this->withoutMiddleware($this->csrfMiddleware());

        $local = $this->localPhone();
        $keep = $this->client('Keep '.$this->token(), $local);
        $duplicate = $this->client('Dup '.$this->token(), '+995'.$local);
        $invalid = $this->client('Bad '.$this->token(), '123');
        $body = 'Hello {client_name}';

        $preview = $this->actingAs($this->admin(), 'backpack')->postJson('/admin/client/sms-preview', [
            'scope' => 'selected',
            'entries' => [$keep->id, $duplicate->id, $invalid->id],
            'body' => $body,
        ]);

        $preview->assertOk();
        $preview->assertJsonPath('queued', 1);
        $preview->assertJsonPath('skipped', 1);
        $preview->assertJsonPath('duplicates', 1);
        $this->assertSame(0, Notification::query()->whereIn('notifiable_id', [$keep->id, $duplicate->id, $invalid->id])->count());

        $send = $this->actingAs($this->admin(), 'backpack')->postJson('/admin/client/sms-send', [
            'scope' => 'selected',
            'entries' => [$keep->id, $duplicate->id, $invalid->id],
            'body' => $body,
        ]);

        $send->assertOk();
        $send->assertJsonPath('queued', 1);

        $row = Notification::query()->where('notifiable_id', $keep->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('Hello '.$keep->name, $row->body);
        $this->assertSame('995'.$local, $row->recipient);
        $this->assertSame(Notification::TYPE_SMS, $row->type);
        $this->assertSame(Notification::STATUS_PENDING, $row->status);
        $this->assertNull($row->sms_template_id);
        $this->assertSame($this->admin()->id, (int) $row->author);
        $this->assertNull(Notification::query()->where('notifiable_id', $duplicate->id)->first());
        $this->assertNull(Notification::query()->where('notifiable_id', $invalid->id)->first());
        $this->assertSame([], $fake->sent);
    }

    public function test_a_template_is_remembered_only_when_the_text_is_unchanged(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $client = $this->client('Tpl '.$this->token(), $this->localPhone());
        $text = 'Hello {client_name}';
        $template = SmsTemplate::query()->create([
            'title' => 'blast-'.uniqid(),
            'text' => $text,
        ]);

        $same = $this->actingAs($this->admin(), 'backpack')->postJson('/admin/client/sms-send', [
            'scope' => 'selected',
            'entries' => [$client->id],
            'body' => $text,
            'sms_template_id' => $template->id,
        ]);

        $same->assertOk();
        $this->assertSame($template->id, (int) Notification::query()->where('notifiable_id', $client->id)->value('sms_template_id'));

        $edited = $this->actingAs($this->admin(), 'backpack')->postJson('/admin/client/sms-send', [
            'scope' => 'selected',
            'entries' => [$client->id],
            'body' => $text.'!',
            'sms_template_id' => $template->id,
        ]);

        $edited->assertOk();
        $this->assertNull(
            Notification::query()->where('notifiable_id', $client->id)->where('body', 'like', '%!')->value('sms_template_id')
        );
    }

    public function test_filtered_and_searched_clients_are_the_only_recipients(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $token = $this->token();
        $match = $this->client('Filter '.$token, $this->localPhone());
        $other = $this->client('Other '.$this->token(), $this->localPhone());
        $body = 'Hello {client_name}';

        $filtered = $this->actingAs($this->admin(), 'backpack')->postJson('/admin/client/sms-send?name='.urlencode($token), [
            'scope' => 'filtered',
            'entries' => [$other->id],
            'body' => $body,
        ]);

        $filtered->assertOk();
        $filtered->assertJsonPath('queued', 1);
        $this->assertSame('Hello '.$match->name, Notification::query()->where('notifiable_id', $match->id)->value('body'));
        $this->assertNull(Notification::query()->where('notifiable_id', $other->id)->first());

        $searchToken = $this->token();
        $found = $this->client('Search '.$searchToken, $this->localPhone());

        $searched = $this->actingAs($this->admin(), 'backpack')->postJson('/admin/client/sms-preview', [
            'scope' => 'filtered',
            'search' => $searchToken,
            'body' => $body,
        ]);

        $searched->assertOk();
        $searched->assertJsonPath('queued', 1);
        $searched->assertJsonPath('sample', 'Hello '.$found->name);
    }

    public function test_unresolved_placeholders_and_an_empty_selection_are_rejected(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $client = $this->client('Nope '.$this->token(), $this->localPhone());
        $before = Notification::query()->count();

        $unresolved = $this->actingAs($this->admin(), 'backpack')->postJson('/admin/client/sms-send', [
            'scope' => 'selected',
            'entries' => [$client->id],
            'body' => 'Link {cabinet_url}',
        ]);

        $unresolved->assertStatus(422);
        $unresolved->assertJsonPath('ok', false);

        $empty = $this->actingAs($this->admin(), 'backpack')->postJson('/admin/client/sms-preview', [
            'scope' => 'selected',
            'entries' => [],
            'body' => 'Hello',
        ]);

        $empty->assertStatus(422);
        $this->assertSame($before, Notification::query()->count());
    }

    public function test_staff_without_permission_cannot_send_and_do_not_see_the_button(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $user = User::factory()->create();
        $user->givePermissionTo('client.list');

        $page = $this->actingAs($user, 'backpack')->get('/admin/client');
        $page->assertOk();
        $page->assertDontSee('id="clientSmsOpen"', false);
        $page->assertDontSee('crud_bulk_actions_general_checkbox', false);

        $send = $this->actingAs($user, 'backpack')->postJson('/admin/client/sms-send', [
            'scope' => 'filtered',
            'body' => 'Hello',
        ]);

        $send->assertForbidden();
    }

    public function test_the_clients_list_offers_sms_for_the_current_filters(): void
    {
        $this->withoutMiddleware($this->csrfMiddleware());

        $page = $this->actingAs($this->admin(), 'backpack')->get('/admin/client');

        $page->assertOk();
        $page->assertSee(__('client.sms.button'), false);
        $page->assertSee('id="clientSmsModal"', false);
        $page->assertSee(__('client.sms.scope_filtered'), false);
        $page->assertSee(__('client.sms.all_warning'), false);
        $page->assertSee('crud_bulk_actions_general_checkbox', false);
        $this->assertTrue(ClientSmsBroadcast::everyone([], ''));
        $this->assertFalse(ClientSmsBroadcast::everyone(['name' => 'Nino'], ''));
        $this->assertFalse(ClientSmsBroadcast::everyone(['client_type' => '0'], ''));
        $this->assertFalse(ClientSmsBroadcast::everyone([], 'nino'));
    }

    protected function client(string $name, string $phone): Client
    {
        return Client::query()->create([
            'name' => $name,
            'phone_number' => $phone,
            'address' => 'sms test',
            'client_type' => 0,
            'personal_id' => 'p'.uniqid(),
        ]);
    }

    protected function localPhone(): string
    {
        return '577'.random_int(100000, 999999);
    }

    protected function token(): string
    {
        return 'smsblast'.uniqid();
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
}
