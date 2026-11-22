<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sets the screen language for every request (global "before" filter):
 * the logged-in user's saved choice (users.locale), else the guest's session choice, else Config\App::$defaultLocale.
 * Language files: app/Language/{id,en}/. Printed documents (app/Views/pdf/) are always Indonesian.
 */
class LocaleFilter implements FilterInterface
{
    /**
     * @param RequestInterface  $request
     * @param list<string>|null $arguments
     *
     * @return null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $locale = current_locale_choice();

        if ($locale !== null && method_exists($request, 'setLocale')) {
            $request->setLocale($locale);
            service('language')->setLocale($locale);
        }

        return null;
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param list<string>|null $arguments
     *
     * @return null
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
