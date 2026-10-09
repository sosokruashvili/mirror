<?php

namespace Tests\Feature;

use Tests\TestCase;

class CabinetHostTest extends TestCase
{
    public function test_cabinet_host_serves_login_and_refuses_admin(): void
    {
        $host = (string) config('cabinet.host');
        $adminCookie = (string) config('session.cookie');

        $login = $this->get('https://'.$host.'/login');

        $login->assertOk();
        $login->assertSee('კაბინეტი', false);
        $login->assertSee('შესვლა', false);

        $names = array_map(
            fn ($cookie) => $cookie->getName(),
            $login->headers->getCookies()
        );

        $this->assertContains('cabinet-session', $names);
        $this->assertNotContains($adminCookie, $names);

        $session = collect($login->headers->getCookies())->first(
            fn ($cookie) => $cookie->getName() === 'cabinet-session'
        );
        $this->assertNotNull($session);
        $this->assertTrue($session->isSecure());
        $this->assertTrue($session->isHttpOnly());
        $this->assertNull($session->getDomain());
        $this->assertHeader($login, 'X-Frame-Options', 'DENY');
        $this->assertHeader($login, 'X-Robots-Tag', 'noindex, nofollow');

        $this->get('https://'.$host.'/')
            ->assertRedirect();

        $home = $this->get('https://'.$host.'/');
        $this->assertStringContainsString('/login', (string) $home->headers->get('Location'));
        $this->assertStringNotContainsString('/admin', (string) $home->headers->get('Location'));

        $this->get('https://'.$host.'/admin')
            ->assertNotFound()
            ->assertSee('გვერდი ვერ მოიძებნა', false);

        $this->get('https://'.$host.'/admin/client')
            ->assertNotFound();
    }

    public function test_admin_host_does_not_serve_the_cabinet_login(): void
    {
        $this->get('https://app.mirrorgallery.ge/login')->assertNotFound();
    }

    protected function assertHeader($response, string $name, string $value): void
    {
        $this->assertSame($value, $response->headers->get($name));
    }
}
