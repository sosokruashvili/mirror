<?php

namespace App\Support\Cabinet;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cabinet
{
    /**
     * Paths a cabinet host is allowed to reach. Everything else, including
     * /admin, is refused before the admin routers run.
     */
    public static function allowsPath(string $path): bool
    {
        $path = trim($path, '/');

        if ($path === '' || $path === 'up' || $path === 'login' || $path === 'logout' || $path === 'access') {
            return true;
        }

        if (preg_match('/^[a-f0-9]{8}$/i', $path) === 1) {
            return true;
        }

        foreach (['orders', 'payments', 'notifications', 'locale'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Private cabinet link for one client. The token is unguessable, so the
     * public login address alone does not open anyone's cabinet.
     */
    public static function accessUrl(string $token): string
    {
        $host = trim((string) config('cabinet.host'));

        return 'https://'.$host.'/'.$token;
    }

    public static function hashAccessToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function isHost(Request $request): bool
    {
        $expected = strtolower(trim((string) config('cabinet.host')));

        return $expected !== '' && strtolower($request->getHost()) === $expected;
    }

    /**
     * Digits only, with a local Georgian number expanded to 995…
     * The same rules run when the password is saved and when the client signs in.
     */
    public static function phoneDigits(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 9) {
            return '995'.$digits;
        }

        return $digits;
    }

    public static function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', ' ').' ₾';
    }

    public static function measure(float|int|string|null $value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Whole days from today until the due date. Negative when overdue.
     */
    public static function daysUntil(?CarbonInterface $due): ?int
    {
        if ($due === null) {
            return null;
        }

        $today = now()->startOfDay();
        $dueDay = $due->copy()->startOfDay();
        $days = $today->diff($dueDay)->days;

        return $dueDay->greaterThanOrEqualTo($today) ? $days : -$days;
    }

    /**
     * green ≥5 days, yellow 3–4, red ≤2 or overdue.
     */
    public static function dueTone(?CarbonInterface $due): ?string
    {
        $days = self::daysUntil($due);

        if ($days === null) {
            return null;
        }

        if ($days >= 5) {
            return 'ok';
        }

        if ($days >= 3) {
            return 'soon';
        }

        return 'urgent';
    }

    public static function dueText(?CarbonInterface $due): string
    {
        $days = self::daysUntil($due);

        if ($days === null) {
            return '';
        }

        if ($days === 0) {
            return __('order.due_today');
        }

        if ($days > 0) {
            return __('order.days_left', ['days' => $days]);
        }

        return __('order.overdue_days', ['days' => abs($days)]);
    }

    /**
     * Same-origin path for a Basset asset. Basset's own URL points at the
     * admin host; the cabinet must load Tabler from its own host.
     */
    public static function assetUrl(string $asset): string
    {
        $url = basset($asset);
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }

    public static function enumLabel(string $group, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $key = $group.'.'.$value;
        $label = __($key);

        return $label === $key ? $value : $label;
    }

    public static function secure(Response $response): Response
    {
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'"
        );

        return $response;
    }
}
