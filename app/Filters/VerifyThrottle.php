<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Cache\Handlers\DummyHandler;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Throttle\Throttler;
use Config\Services;

/**
 * Rate limit for the public verification pages (GET verify, verify/{token}): at most LIMIT requests per minute per
 * visitor IP (Config\App::$proxyIPs gives the real IP behind the reverse proxy), then 429. Uses the cache (Redis).
 */
class VerifyThrottle implements FilterInterface
{
    /** Requests per minute per IP. */
    public const LIMIT = 20;

    /**
     * @param RequestInterface $request   Request.
     * @param list<string>|null $arguments Unused.
     *
     * @return ResponseInterface|null 429 response when the limit is reached.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $key       = 'verify-' . md5($request->getIPAddress());
        $throttler = new Throttler(self::cache());

        if ($throttler->check($key, self::LIMIT, MINUTE) === false) {
            return service('response')
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) max(1, $throttler->getTokenTime()))
                ->setBody('Terlalu banyak permintaan. Coba lagi sebentar lagi. / Too many requests, please try again shortly.');
        }

        return null;
    }

    /**
     * The app cache, or the file cache when it fell back to the dummy handler (e.g. Redis unreachable or a wrong
     * password): the dummy handler stores nothing, which would switch the rate limit off without notice.
     *
     * @return CacheInterface
     */
    private static function cache(): CacheInterface
    {
        $cache = cache();

        if (! $cache instanceof DummyHandler) {
            return $cache;
        }

        $config          = clone config('Cache');
        $config->handler = 'file';

        return Services::cache($config, false);
    }

    /**
     * @param RequestInterface  $request   Request.
     * @param ResponseInterface $response  Response.
     * @param list<string>|null $arguments Unused.
     *
     * @return ResponseInterface|null
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
