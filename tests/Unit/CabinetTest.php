<?php

namespace Tests\Unit;

use App\Support\Cabinet\Cabinet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class CabinetTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_phone_numbers_normalize_to_the_same_digits(): void
    {
        $this->assertSame('995555123456', Cabinet::phoneDigits('+995 555 12 34 56'));
        $this->assertSame('995555123456', Cabinet::phoneDigits('555123456'));
        $this->assertSame('995555123456', Cabinet::phoneDigits('995555123456'));
        $this->assertSame('', Cabinet::phoneDigits(''));
        $this->assertSame('', Cabinet::phoneDigits(null));
    }

    public function test_only_cabinet_paths_are_allowed(): void
    {
        $this->assertTrue(Cabinet::allowsPath('/'));
        $this->assertTrue(Cabinet::allowsPath('01a39323'));
        $this->assertFalse(Cabinet::allowsPath('zzzzzzzz'));
        $this->assertTrue(Cabinet::allowsPath('orders'));
        $this->assertTrue(Cabinet::allowsPath('orders/15'));
        $this->assertTrue(Cabinet::allowsPath('payments'));
        $this->assertTrue(Cabinet::allowsPath('notifications'));
        $this->assertTrue(Cabinet::allowsPath('locale/ka'));

        $this->assertFalse(Cabinet::allowsPath('admin'));
        $this->assertFalse(Cabinet::allowsPath('admin/login'));
        $this->assertFalse(Cabinet::allowsPath('admin/client'));
        $this->assertFalse(Cabinet::allowsPath('login-extra'));
        $this->assertFalse(Cabinet::allowsPath('orders-extra'));
    }

    public function test_host_match_is_exact(): void
    {
        config(['cabinet.host' => 'client.mirrorgallery.ge']);

        $this->assertTrue(Cabinet::isHost(Request::create('https://client.mirrorgallery.ge/login')));
        $this->assertFalse(Cabinet::isHost(Request::create('https://app.mirrorgallery.ge/admin')));
        $this->assertFalse(Cabinet::isHost(Request::create('https://evilclient.mirrorgallery.ge/login')));
    }

    public function test_due_tones_follow_the_workshop_bands(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 15:00:00', config('app.timezone')));

        $this->assertSame(5, Cabinet::daysUntil(Carbon::parse('2026-10-13')));
        $this->assertSame('ok', Cabinet::dueTone(Carbon::parse('2026-10-13')));

        $this->assertSame(3, Cabinet::daysUntil(Carbon::parse('2026-10-11')));
        $this->assertSame('soon', Cabinet::dueTone(Carbon::parse('2026-10-11')));

        $this->assertSame(2, Cabinet::daysUntil(Carbon::parse('2026-10-10')));
        $this->assertSame('urgent', Cabinet::dueTone(Carbon::parse('2026-10-10')));

        $this->assertSame(0, Cabinet::daysUntil(Carbon::parse('2026-10-08')));
        $this->assertSame('urgent', Cabinet::dueTone(Carbon::parse('2026-10-08')));

        $this->assertSame(-1, Cabinet::daysUntil(Carbon::parse('2026-10-07')));
        $this->assertSame('urgent', Cabinet::dueTone(Carbon::parse('2026-10-07')));
    }
}
